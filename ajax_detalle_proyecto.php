<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset("utf8");

$id_proyecto = intval($_POST['id_proyecto'] ?? 0);
$id_oficina  = intval($_POST['id_oficina']  ?? 0);
$desde       = $_POST['desde'] ?? date('Y-m-01');
$hasta       = $_POST['hasta'] ?? date('Y-m-t');

if ($id_proyecto === 0 || $id_oficina === 0) {
    echo json_encode(['success' => false, 'msg' => 'Parámetros inválidos.']); exit;
}

$stmt = $conn->prepare("
    SELECT m.id, m.fecha, m.concepto, m.intermediario, m.doc_soporte,
           m.importe_recibido, m.importe_entregado, m.ESTADO,
           u.usuario AS nombre_usuario,
           r.REPOSICION AS inf_fin
    FROM movimientos m
    LEFT JOIN usuarios u ON m.ID_USUARIO = u.id
    LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
    WHERE m.ID_PROYECTO = ?
      AND m.ID_OFICINA = ?
      AND m.fecha BETWEEN ? AND ?
    ORDER BY m.fecha ASC, m.id ASC
");
$stmt->bind_param("iiss", $id_proyecto, $id_oficina, $desde, $hasta);
$stmt->execute();
$res = $stmt->get_result();

$movimientos = [];
$total_ing = 0;
$total_egr = 0;
while ($row = $res->fetch_assoc()) {
    if ($row['ESTADO'] === 'A') {
        $total_ing += $row['importe_recibido'];
        $total_egr += $row['importe_entregado'];
    }
    $movimientos[] = $row;
}

echo json_encode([
    'success'     => true,
    'movimientos' => $movimientos,
    'total_ing'   => $total_ing,
    'total_egr'   => $total_egr,
    'saldo'       => $total_ing - $total_egr
]);
