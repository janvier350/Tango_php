<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset("utf8");

$action = $_POST['action'] ?? '';

if ($action === 'check') {
    $doc = trim($_POST['doc'] ?? '');
    if ($doc === '') { echo json_encode(['exists' => false]); exit; }

    $stmt = $conn->prepare("SELECT id FROM movimientos WHERE doc_soporte = ?");
    $stmt->bind_param("s", $doc);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        // Buscar más detalle para informar al usuario
        $info = $conn->prepare("SELECT m.fecha, m.concepto, u.usuario, o.OFICINA
            FROM movimientos m
            LEFT JOIN usuarios u ON m.ID_USUARIO = u.id
            LEFT JOIN CTR_OFICINA o ON m.ID_OFICINA = o.ID_OFICINA
            WHERE m.doc_soporte = ? LIMIT 1");
        $info->bind_param("s", $doc);
        $info->execute();
        $det = $info->get_result()->fetch_assoc();
        echo json_encode([
            'exists'  => true,
            'fecha'   => $det['fecha']   ?? '',
            'concepto'=> $det['concepto'] ?? '',
            'usuario' => $det['usuario'] ?? '',
            'oficina' => $det['OFICINA'] ?? ''
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
    exit;
}

echo json_encode(['exists' => false]);
