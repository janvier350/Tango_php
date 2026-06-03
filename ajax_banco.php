<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset("utf8");

$action = $_POST['action'] ?? '';

if ($action === 'check') {
    $nombre = strtoupper(trim($_POST['nombre'] ?? ''));
    if ($nombre === '') { echo json_encode(['exists' => false]); exit; }
    $stmt = $conn->prepare("SELECT id FROM cat_bancos WHERE UPPER(nombre) = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    echo json_encode(['exists' => $stmt->get_result()->num_rows > 0]);
    exit;
}

if ($action === 'crear') {
    $nombre = strtoupper(trim($_POST['nombre'] ?? ''));

    if ($nombre === '') {
        echo json_encode(['success' => false, 'msg' => 'El nombre del banco es obligatorio.']); exit;
    }

    $stmt = $conn->prepare("SELECT id FROM cat_bancos WHERE UPPER(nombre) = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'msg' => 'Ya existe un banco con ese nombre.']); exit;
    }

    $ins = $conn->prepare("INSERT INTO cat_bancos (nombre) VALUES (?)");
    $ins->bind_param("s", $nombre);

    if ($ins->execute()) {
        echo json_encode(['success' => true, 'nombre' => $nombre]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al guardar: ' . $ins->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Acción no válida.']);
