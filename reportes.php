<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

// Valores por defecto: Mes y Año actual
$mes_sel = isset($_GET['mes']) ? $_GET['mes'] : date('m');
$anio_sel = isset($_GET['anio']) ? $_GET['anio'] : date('Y');

// --- CONSULTA DE TOTALES DEL PERÍODO ---
$sql_totales = "SELECT 
    SUM(CASE WHEN tipo='Ingreso' THEN monto ELSE 0 END) as ingresos,
    SUM(CASE WHEN tipo='Egreso' THEN monto ELSE 0 END) as egresos
    FROM movimientos 
    WHERE MONTH(fecha) = '$mes_sel' AND YEAR(fecha) = '$anio_sel'";
$res_totales = $conn->query($sql_totales)->fetch_assoc();

$t_ing = $res_totales['ingresos'] ?? 0;
$t_egr = $res_totales['egresos'] ?? 0;
$t_saldo = $t_ing - $t_egr;

// --- CONSULTA GASTOS POR CATEGORÍA (Top Gastos) ---
$sql_cat = "SELECT categoria, SUM(monto) as total 
            FROM movimientos 
            WHERE tipo='Egreso' AND MONTH(fecha) = '$mes_sel' AND YEAR(fecha) = '$anio_sel'
            GROUP BY categoria ORDER BY total DESC";
$res_cat = $conn->query($sql_cat);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes Mensuales - JVARAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="small fw-bold text-muted">Seleccionar Mes</label>
                        <select name="mes" class="form-select">
                            <?php
                            $meses = ["01"=>"Enero","02"=>"Febrero","03"=>"Marzo","04"=>"Abril","05"=>"Mayo","06"=>"Junio","07"=>"Julio","08"=>"Agosto","09"=>"Septiembre","10"=>"Octubre","11"=>"Noviembre","12"=>"Diciembre"];
                            foreach($meses as $num => $nombre) {
                                $sel = ($num == $mes_sel) ? "selected" : "";
                                echo "<option value='$num' $sel>$nombre</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-bold text-muted">Seleccionar Año</label>
                        <select name="anio" class="form-select">
                            <option value="2024" <?php if($anio_sel=="2024") echo "selected"; ?>>2024</option>
                            <option value="2025" <?php if($anio_sel=="2025") echo "selected"; ?>>2025</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">GENERAR REPORTE</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold">Resumen de Caja: <?php echo $meses[$mes_sel]; ?></div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <tr>
                                <td class="ps-3 py-3">Ingresos Mensuales</td>
                                <td class="text-end pe-3 py-3 text-success fw-bold">$<?php echo number_format($t_ing, 2); ?></td>
                            </tr>
                            <tr>
                                <td class="ps-3 py-3">Egresos Mensuales</td>
                                <td class="text-end pe-3 py-3 text-danger fw-bold">$<?php echo number_format($t_egr, 2); ?></td>
                            </tr>
                            <tr class="table-dark">
                                <td class="ps-3 py-3 fw-bold">SALDO DEL PERÍODO</td>
                                <td class="text-end pe-3 py-3 fw-bold">$<?php echo number_format($t_saldo, 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold">Gastos por Categoría</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr class="small text-muted">
                                    <th class="ps-3">Categoría</th>
                                    <th class="text-end pe-3">Total Gastado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($res_cat->num_rows > 0): 
                                    while($c = $res_cat->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3 py-2"><?php echo $c['categoria']; ?></td>
                                        <td class="text-end pe-3 py-2 fw-bold text-danger">$<?php echo number_format($c['total'], 2); ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="2" class="text-center p-3 text-muted small">Sin gastos registrados en este mes.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>