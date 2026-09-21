<?php
// Exportación de movimientos a Excel (CSV con BOM UTF-8 y delimitador ';',
// que Excel en español abre en columnas con acentos y números correctos).
// Sin librerías externas para no depender del hosting.
date_default_timezone_set('America/Guayaquil');
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
$conn->set_charset("utf8");

$id_rol         = intval($_SESSION["user_rol"] ?? 0);
$id_oficina_ses = intval($_SESSION["oficina_ID"]);
$es_admin       = in_array($id_rol, [3, 4]); // CEO / Administración

// --- Caja a exportar ---
// Admin: puede elegir una caja, o TODAS (incluye Mensajería). Otros: solo la suya.
$req_of        = $_GET['id_oficina'] ?? '';
$modo_todas    = false;
$filtro_oficina = 0;
if ($es_admin) {
    if (strtoupper((string)$req_of) === 'ALL') {
        $modo_todas = true;
    } elseif (ctype_digit((string)$req_of) && intval($req_of) > 0) {
        $filtro_oficina = intval($req_of);
    } else {
        $filtro_oficina = $id_oficina_ses;
    }
} else {
    $filtro_oficina = $id_oficina_ses;
}

// --- Filtros ---
$re_fecha = '/^\d{4}-\d{2}-\d{2}$/';
$desde  = (isset($_GET['desde']) && preg_match($re_fecha, $_GET['desde'])) ? $_GET['desde'] : '';
$hasta  = (isset($_GET['hasta']) && preg_match($re_fecha, $_GET['hasta'])) ? $_GET['hasta'] : '';
$tipo   = $_GET['tipo']   ?? '';   // '', 'Ingreso', 'Egreso'
$estado = $_GET['estado'] ?? 'A';  // 'A' activos, 'I' anulados, '' todos

// --- Query ---
$sql = "SELECT m.id, m.fecha, m.ID_OFICINA, o.OFICINA AS caja,
               c.nombre AS categoria, r.REPOSICION AS cuenta,
               p.PROYECTO AS proyecto,
               m.intermediario AS beneficiario, m.INTERMEDIARIO2 AS intermediario,
               m.concepto, m.empresa, m.doc_soporte, m.banco, m.cheque_num,
               m.importe_recibido, m.importe_entregado, m.ESTADO,
               ureg.usuario AS registrado_por, urev.usuario AS revisado_por,
               m.FECHA_REGISTRO_I_E
        FROM movimientos m
        LEFT JOIN CTR_OFICINA o    ON m.ID_OFICINA = o.ID_OFICINA
        LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
        LEFT JOIN cat_reposiciones c ON r.ID_CAT_REPOCICIONES = c.id
        LEFT JOIN PROYECTOS p      ON m.ID_PROYECTO = p.ID_PROYECTO
        LEFT JOIN usuarios ureg    ON m.ID_USUARIO = ureg.id
        LEFT JOIN usuarios urev    ON m.ID_USUARIO_REVISA = urev.id
        WHERE 1=1";
$params = [];
$types  = "";
if (!$modo_todas) { $sql .= " AND m.ID_OFICINA = ?"; $params[] = $filtro_oficina; $types .= "i"; }
if ($desde !== '') { $sql .= " AND m.fecha >= ?"; $params[] = $desde; $types .= "s"; }
if ($hasta !== '') { $sql .= " AND m.fecha <= ?"; $params[] = $hasta; $types .= "s"; }
if ($tipo === 'Ingreso') { $sql .= " AND m.importe_recibido > 0"; }
elseif ($tipo === 'Egreso') { $sql .= " AND m.importe_entregado > 0"; }
if ($estado === 'A' || $estado === 'I') { $sql .= " AND m.ESTADO = ?"; $params[] = $estado; $types .= "s"; }
$sql .= " ORDER BY o.OFICINA ASC, m.fecha ASC, m.id ASC";

$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();

// --- Nombre del archivo ---
$sufijo = $modo_todas ? 'TODAS' : 'caja';
$rango  = ($desde || $hasta) ? ('_' . ($desde ?: 'inicio') . '_a_' . ($hasta ?: 'hoy')) : '';
$filename = 'movimientos_' . $sufijo . $rango . '_' . date('Ymd_His') . '.csv';

// --- Salida CSV ---
if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // BOM UTF-8 para que Excel muestre bien los acentos
$out = fopen('php://output', 'w');

$cabecera = ['Secuencia','Fecha','Caja','Categoria','Cuenta (Inf.Fin)','Proyecto',
             'Beneficiario','Intermediario','Concepto','Empresa','Doc. Soporte',
             'Banco','Cheque','Ingreso','Egreso','Neto','Saldo periodo','Estado',
             'Registrado por','Revisado por','Fecha registro'];
fputcsv($out, $cabecera, ';');

$fnum = function ($n) { return number_format((float)$n, 2, ',', ''); };
$caja_actual = null;
$saldo = 0.0;
$tot_ing = 0.0; $tot_egr = 0.0;

while ($m = $res->fetch_assoc()) {
    // Saldo corrido por caja (solo activos). Se reinicia al cambiar de caja.
    if ($caja_actual !== $m['ID_OFICINA']) { $caja_actual = $m['ID_OFICINA']; $saldo = 0.0; }
    $ing = (float)$m['importe_recibido'];
    $egr = (float)$m['importe_entregado'];
    $neto = $ing - $egr;
    $anulado = ($m['ESTADO'] === 'I');
    if (!$anulado) { $saldo += $neto; $tot_ing += $ing; $tot_egr += $egr; }

    fputcsv($out, [
        $m['id'],
        date('d/m/Y', strtotime($m['fecha'])),
        $m['caja'] ?? '',
        $m['categoria'] ?? '',
        $m['cuenta'] ?? '',
        $m['proyecto'] ?? '',
        $m['beneficiario'] ?? '',
        $m['intermediario'] ?? '',
        $m['concepto'] ?? '',
        $m['empresa'] ?? '',
        $m['doc_soporte'] ?? '',
        $m['banco'] ?? '',
        $m['cheque_num'] ?? '',
        $ing > 0 ? $fnum($ing) : '',
        $egr > 0 ? $fnum($egr) : '',
        $fnum($neto),
        $anulado ? '' : $fnum($saldo),
        $anulado ? 'ANULADO' : 'ACTIVO',
        $m['registrado_por'] ?? '',
        $m['revisado_por'] ?? '',
        $m['FECHA_REGISTRO_I_E'] ? date('d/m/Y H:i', strtotime($m['FECHA_REGISTRO_I_E'])) : '',
    ], ';');
}

// Fila de totales (solo activos)
fputcsv($out, [], ';');
fputcsv($out, ['','','','','','','','','','','','','TOTALES',
               $fnum($tot_ing), $fnum($tot_egr), $fnum($tot_ing - $tot_egr), '', '', '', '', ''], ';');

fclose($out);
exit;
