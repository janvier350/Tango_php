<?php
date_default_timezone_set('America/Guayaquil');
header('Content-Type: text/html; charset=utf-8');
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
$conn->set_charset("utf8");

$id_rol           = intval($_SESSION["user_rol"] ?? 0);
$id_oficina_ses   = intval($_SESSION["oficina_ID"]);
$nombre_caja      = $_SESSION["oficina"];

// --- Oficina (caja) del reporte ---
// CEO (3) y Administracion (4) pueden pedir el reporte de cualquier caja.
// El operador de recepcion 403 puede pedir el de cajas independientes (ej. Mensajeria).
// El resto siempre obtiene el de su propia oficina de sesion.
$req_oficina = intval($_GET['id_oficina'] ?? 0);
$puede_otras = in_array($id_rol, [3, 4]);
if (!$puede_otras && $id_rol == 2 && strtoupper(trim($_SESSION["oficina"])) === '403'
    && $req_oficina > 0 && col_existe($conn, 'CTR_OFICINA', 'ES_INDEPENDIENTE')) {
    $chk = $conn->prepare("SELECT ES_INDEPENDIENTE FROM CTR_OFICINA WHERE ID_OFICINA = ?");
    $chk->bind_param("i", $req_oficina);
    $chk->execute();
    if ((int)($chk->get_result()->fetch_assoc()['ES_INDEPENDIENTE'] ?? 0) === 1) {
        $puede_otras = true;
    }
}
if ($req_oficina > 0 && $puede_otras && $req_oficina !== $id_oficina_ses) {
    $id_oficina = $req_oficina;
    // Nombre real de la caja solicitada
    $stmt_ofi = $conn->prepare("SELECT OFICINA FROM CTR_OFICINA WHERE ID_OFICINA = ?");
    $stmt_ofi->bind_param("i", $id_oficina);
    $stmt_ofi->execute();
    $row_ofi = $stmt_ofi->get_result()->fetch_assoc();
    if (!$row_ofi) { header("Location: index.php"); exit; }
    $nombre_caja = $row_ofi['OFICINA'];
} else {
    $id_oficina = $id_oficina_ses;
}

$meses_es = [1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
             7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'];

// --- Filtros de fecha: rango Desde/Hasta (prioridad) o mes/anio ---
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$re_fecha  = '/^\d{4}-\d{2}-\d{2}$/';
$usar_rango = (preg_match($re_fecha, $desde) && preg_match($re_fecha, $hasta));

if ($usar_rango) {
    if ($desde > $hasta) { $tmp = $desde; $desde = $hasta; $hasta = $tmp; }
    $etiqueta_periodo = 'DEL ' . date('d/m/Y', strtotime($desde)) . ' AL ' . date('d/m/Y', strtotime($hasta));
    $fecha_corte = $desde; // saldo inicial: movimientos anteriores a "desde"
} else {
    $mes  = isset($_GET['mes'])  ? intval($_GET['mes'])  : intval(date('n'));
    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));
    if ($mes < 1 || $mes > 12) $mes = intval(date('n'));
    if ($anio < 2000 || $anio > 2100) $anio = intval(date('Y'));
    $etiqueta_periodo = $meses_es[$mes] . ' ' . $anio;
    $fecha_corte = sprintf('%04d-%02d-01', $anio, $mes);
}

// --- Saldo inicial: acumulado de movimientos activos ANTERIORES a la fecha de corte ---
$stmt_ini = $conn->prepare("SELECT COALESCE(SUM(importe_recibido - importe_entregado),0) AS saldo_ini
                            FROM movimientos
                            WHERE ID_OFICINA = ? AND ESTADO <> 'I' AND fecha < ?");
$stmt_ini->bind_param("is", $id_oficina, $fecha_corte);
$stmt_ini->execute();
$saldo_inicial = (float)($stmt_ini->get_result()->fetch_assoc()['saldo_ini'] ?? 0);

// --- Movimientos del periodo ---
if ($usar_rango) {
    $stmt = $conn->prepare("SELECT m.*, r.REPOSICION AS cuenta
                            FROM movimientos m
                            LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
                            WHERE m.ID_OFICINA = ? AND m.fecha BETWEEN ? AND ?
                            ORDER BY m.fecha ASC, m.id ASC");
    $stmt->bind_param("iss", $id_oficina, $desde, $hasta);
} else {
    $stmt = $conn->prepare("SELECT m.*, r.REPOSICION AS cuenta
                            FROM movimientos m
                            LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
                            WHERE m.ID_OFICINA = ? AND YEAR(m.fecha) = ? AND MONTH(m.fecha) = ?
                            ORDER BY m.fecha ASC, m.id ASC");
    $stmt->bind_param("iii", $id_oficina, $anio, $mes);
}
$stmt->execute();
$movs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_ing = 0; $total_gas = 0;
foreach ($movs as $m) {
    if ($m['ESTADO'] == 'I') continue;
    $total_ing += (float)$m['importe_recibido'];
    $total_gas += (float)$m['importe_entregado'];
}
$saldo_final = $saldo_inicial + $total_ing - $total_gas;

$fecha_impresion = date('d/m/Y H:i:s');
$usuario = $_SESSION["user_name"] ?? ($_SESSION["user_id"] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>Reporte <?php echo htmlspecialchars($nombre_caja) . ' - ' . $etiqueta_periodo; ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #222; margin: 20px; }

        .toolbar { text-align: center; margin-bottom: 18px; }
        .toolbar button, .toolbar a {
            font-size: 13px; padding: 8px 18px; border-radius: 6px; cursor: pointer;
            border: 1px solid #444; text-decoration: none; margin: 0 4px;
        }
        .btn-print { background: #212529; color: #fff; }
        .btn-back  { background: #f1f1f1; color: #333; }

        .header { text-align: center; border-bottom: 2px solid #212529; padding-bottom: 10px; margin-bottom: 15px; }
        .header h2 { margin: 0 0 4px; letter-spacing: 1px; }
        .header .caja { display:inline-block; background:#212529; color:#fff; padding:2px 10px; border-radius:4px; font-weight:bold; font-size:13px; }
        .header .periodo { margin-top: 6px; font-size: 15px; font-weight: bold; color:#0097b2; }
        .header .fecha-imp { margin-top: 3px; font-size: 11px; color: #666; }

        .firmas { display: flex; justify-content: space-around; gap: 40px; margin-top: 70px; page-break-inside: avoid; }
        .firma-box { text-align: center; width: 45%; }
        .firma-linea { border-top: 1.5px solid #000; margin-bottom: 6px; }
        .firma-rol { font-weight: bold; font-size: 12px; letter-spacing: .5px; }
        .firma-cap { font-size: 10px; color: #666; }

        .resumen { display: flex; gap: 10px; justify-content: center; margin-bottom: 15px; flex-wrap: wrap; }
        .card-r { border: 1px solid #ccc; border-radius: 6px; padding: 8px 16px; text-align: center; min-width: 150px; }
        .card-r .lbl { font-size: 11px; color: #666; text-transform: uppercase; }
        .card-r .val { font-size: 16px; font-weight: 800; }
        .val-ing { color: #198754; }
        .val-gas { color: #dc3545; }
        .val-ini { color: #555; }
        .val-fin { color: #0d6efd; }

        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #999; padding: 4px 6px; }
        thead th { background: #d5d5d5; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .cen { text-align: center; }
        .ing { color: #198754; }
        .gas { color: #dc3545; }
        .saldo-col { background: #eef4ff; font-weight: bold; }
        tr.anulado td { background: #f8d7da; color:#a33; text-decoration: line-through; }
        tfoot td { font-weight: bold; background: #efefef; }
        .fila-ini td { background: #fff8e1; font-weight: bold; }

        .footer-print { margin-top: 25px; font-size: 10px; color: #888; border-top: 1px solid #ddd; padding-top: 8px; text-align: center; font-style: italic; }

        @media print {
            body { margin: 0; }
            .toolbar { display: none; }
            @page { size: landscape; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn-print" onclick="window.print();">🖨 Imprimir / Guardar PDF</button>
        <a class="btn-back" href="javascript:history.back()">← Volver</a>
    </div>

    <div class="header">
        <h2>DETALLE DE MOVIMIENTOS</h2>
        <span class="caja"><?php echo htmlspecialchars($nombre_caja); ?></span>
        <div class="periodo"><?php echo htmlspecialchars($etiqueta_periodo); ?></div>
        <div class="fecha-imp">Fecha de impresión: <?php echo $fecha_impresion; ?></div>
    </div>

    <div class="resumen">
        <div class="card-r"><div class="lbl">Saldo Inicial</div><div class="val val-ini">$<?php echo number_format($saldo_inicial,2); ?></div></div>
        <div class="card-r"><div class="lbl">Ingresos</div><div class="val val-ing">$<?php echo number_format($total_ing,2); ?></div></div>
        <div class="card-r"><div class="lbl">Gastos</div><div class="val val-gas">$<?php echo number_format($total_gas,2); ?></div></div>
        <div class="card-r"><div class="lbl">Saldo Final</div><div class="val val-fin">$<?php echo number_format($saldo_final,2); ?></div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th><th>FECHA</th><th>CUENTA</th><th>CONCEPTO</th>
                <th>PROVEEDOR</th><th>EMPRESA</th><th>DOC.</th>
                <th>INGRESO (+)</th><th>GASTO (-)</th><th>SALDO</th><th>ESTADO</th>
            </tr>
        </thead>
        <tbody>
            <tr class="fila-ini">
                <td colspan="9" class="num">SALDO INICIAL (arrastre del período anterior)</td>
                <td class="num saldo-col">$<?php echo number_format($saldo_inicial,2); ?></td>
                <td></td>
            </tr>
            <?php
            $saldo = $saldo_inicial;
            foreach ($movs as $m):
                $anulado = ($m['ESTADO'] == 'I');
                if (!$anulado) $saldo += ($m['importe_recibido'] - $m['importe_entregado']);
                $aprobado = ($m['ID_USUARIO_REVISA'] > 0);
            ?>
            <tr class="<?php echo $anulado ? 'anulado' : ''; ?>">
                <td class="cen"><?php echo $m['id']; ?></td>
                <td class="cen"><?php echo date('d-m-Y', strtotime($m['fecha'])); ?></td>
                <td><?php echo htmlspecialchars($m['cuenta'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($m['concepto']); ?></td>
                <td><?php echo htmlspecialchars($m['intermediario']); ?></td>
                <td><?php echo htmlspecialchars($m['empresa']); ?></td>
                <td class="cen"><?php echo htmlspecialchars($m['doc_soporte']); ?></td>
                <td class="num ing"><?php echo $m['importe_recibido'] > 0 ? '$'.number_format($m['importe_recibido'],2) : '-'; ?></td>
                <td class="num gas"><?php echo $m['importe_entregado'] > 0 ? '$'.number_format($m['importe_entregado'],2) : '-'; ?></td>
                <td class="num saldo-col"><?php echo $anulado ? '-' : '$'.number_format($saldo,2); ?></td>
                <td class="cen"><?php echo $anulado ? 'Anulado' : ($aprobado ? 'Aprobado' : 'Pendiente'); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($movs)): ?>
            <tr><td colspan="11" class="cen" style="padding:15px;color:#888;">No hay movimientos registrados en el período <?php echo htmlspecialchars($etiqueta_periodo); ?>.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="num">TOTALES DEL PERÍODO</td>
                <td class="num ing">$<?php echo number_format($total_ing,2); ?></td>
                <td class="num gas">$<?php echo number_format($total_gas,2); ?></td>
                <td class="num saldo-col">$<?php echo number_format($saldo_final,2); ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="firmas">
        <div class="firma-box">
            <div class="firma-linea"></div>
            <div class="firma-rol"><?php echo htmlspecialchars(strtoupper($nombre_caja)); ?></div>
            <div class="firma-cap">Elabora / Entrega Conforme</div>
        </div>
        <div class="firma-box">
            <div class="firma-linea"></div>
            <div class="firma-rol">ADMINISTRACIÓN</div>
            <div class="firma-cap">Revisa / Aprueba</div>
        </div>
    </div>

    <div class="footer-print">
        Reporte generado por: <?php echo htmlspecialchars($usuario); ?> | Caja: <?php echo htmlspecialchars($nombre_caja); ?> | Período: <?php echo htmlspecialchars($etiqueta_periodo); ?> | Impreso: <?php echo $fecha_impresion; ?>
    </div>
</body>
</html>
