<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset("utf8");

$id_reposicion = intval($_POST['id_reposicion'] ?? 0);
$id_oficina    = intval($_POST['id_oficina']    ?? 0);
$desde         = $_POST['desde'] ?? date('Y-m-01');
$hasta         = $_POST['hasta'] ?? date('Y-m-t');

if ($id_reposicion === 0 || $id_oficina === 0) {
    echo json_encode(['success' => false, 'msg' => 'Parámetros inválidos.']); exit;
}

$stmt = $conn->prepare("
    SELECT m.id, m.fecha, m.concepto, m.intermediario, m.doc_soporte,
           m.importe_recibido, m.ESTADO, u.usuario AS nombre_usuario
    FROM movimientos m
    LEFT JOIN usuarios u ON m.ID_USUARIO = u.id
    WHERE m.inf_fin = ?
      AND m.ID_OFICINA = ?
      AND m.importe_recibido > 0
      AND m.fecha BETWEEN ? AND ?
    ORDER BY m.fecha ASC, m.id ASC
");
$stmt->bind_param("iiss", $id_reposicion, $id_oficina, $desde, $hasta);
$stmt->execute();
$res = $stmt->get_result();

$movimientos = [];
$total = 0;
while ($row = $res->fetch_assoc()) {
    if ($row['ESTADO'] === 'A') $total += $row['importe_recibido'];
    $movimientos[] = $row;
}

echo json_encode(['success' => true, 'movimientos' => $movimientos, 'total' => $total]);
