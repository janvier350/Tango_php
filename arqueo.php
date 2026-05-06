<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

// Calcular Saldo del Sistema (Ingresos - Egresos)
$res_saldo = $conn->query("SELECT SUM(CASE WHEN tipo='Ingreso' THEN monto ELSE -monto END) as saldo FROM movimientos");
$saldo_sistema = $res_saldo->fetch_assoc()['saldo'] ?? 0;

$mensaje = "";
if (isset($_POST['guardar_arqueo'])) {
    $real = $_POST['total_efectivo'];
    $dif = $real - $saldo_sistema;
    $obs = $conn->real_escape_string($_POST['obs']);
    
    $sql = "INSERT INTO arqueos (total_efectivo_real, saldo_sistema, diferencia, observaciones) VALUES ($real, $saldo_sistema, $dif, '$obs')";
    if($conn->query($sql)) $mensaje = "<div class='alert alert-success'>Arqueo guardado exitosamente.</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Arqueo de Caja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css">
    <script>
        function calcular() {
            let total = 0;
            document.querySelectorAll('.denominacion').forEach(input => {
                let valor = parseFloat(input.getAttribute('data-valor'));
                let cantidad = parseFloat(input.value) || 0;
                total += valor * cantidad;
            });
            document.getElementById('total_efectivo').value = total.toFixed(2);
            document.getElementById('display_total').innerText = total.toFixed(2);
            
            let saldo_sis = <?php echo $saldo_sistema; ?>;
            let dif = total - saldo_sis;
            let elDif = document.getElementById('display_dif');
            elDif.innerText = dif.toFixed(2);
            elDif.className = dif < 0 ? 'text-danger fw-bold' : 'text-success fw-bold';
        }
    </script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">CALCULADORA DE EFECTIVO (ARQUEO)</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php 
                            $billetes = [20, 10, 5, 1, 0.50, 0.25, 0.10, 0.05, 0.01];
                            foreach($billetes as $b): ?>
                            <div class="col-6 mb-2">
                                <label class="small fw-bold">Denom. $<?php echo number_format($b, 2); ?></label>
                                <input type="number" class="form-control form-control-sm denominacion" data-valor="<?php echo $b; ?>" oninput="calcular()" placeholder="0">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow-sm border-top border-primary border-5">
                    <div class="card-body text-center">
                        <?php echo $mensaje; ?>
                        <h5 class="text-muted">TOTAL EFECTIVO REAL</h5>
                        <h1 class="display-4 fw-bold text-dark">$<span id="display_total">0.00</span></h1>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Saldo en Sistema:</span>
                            <span class="fw-bold">$<?php echo number_format($saldo_sistema, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Diferencia (Faltante/Sobrante):</span>
                            <span id="display_dif" class="fw-bold">$0.00</span>
                        </div>
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="total_efectivo" id="total_efectivo" value="0">
                            <textarea name="obs" class="form-control mb-2" placeholder="Observaciones del arqueo"></textarea>
                            <button name="guardar_arqueo" class="btn btn-primary w-100 fw-bold">CERRAR CAJA Y GUARDAR ARQUEO</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>