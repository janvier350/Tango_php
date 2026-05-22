<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json');

$dia       = isset($_GET['dia'])       ? intval($_GET['dia'])       : 0;
$mes       = isset($_GET['mes'])       ? intval($_GET['mes'])       : 0;
$anio      = isset($_GET['anio'])      ? intval($_GET['anio'])      : 0;
$id_oficina = isset($_GET['id_oficina']) ? intval($_GET['id_oficina']) : 0;

if (!$dia || !$mes || !$anio || !$id_oficina) {
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

$fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);

$sql = "SELECT
            m.id,
            m.fecha,
            m.empresa,
            m.concepto,
            m.intermediario,
            m.importe_recibido,
            m.importe_entregado,
            m.vale_ref,
            m.banco,
            m.cheque_num,
            m.ESTADO,
            r.REPOSICION AS inf_fin_nombre,
            u.usuario AS nombre_revisor 
        FROM movimientos m
        LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION
        LEFT JOIN usuarios u ON m.ID_USUARIO_REVISA = u.id 
        WHERE DATE(m.fecha) = ? AND m.ID_OFICINA = ?
        ORDER BY m.fecha ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $fecha, $id_oficina);
$stmt->execute();
$result = $stmt->get_result();

$movimientos = [];
while ($row = $result->fetch_assoc()) {
    $movimientos[] = $row;
}

echo json_encode($movimientos);
