<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['user_rol'], [3, 4])) {
    echo json_encode(['success' => false, 'msg' => 'No autorizado.']);
    exit;
}

$action      = $_POST['action'] ?? '';
$id          = intval($_POST['id'] ?? 0);
$informacion = trim($_POST['informacion'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'msg' => 'ID inválido.']);
    exit;
}

if ($action === 'guardar_categoria') {
    $stmt = $conn->prepare("UPDATE cat_reposiciones SET informacion = ? WHERE id = ?");
    $stmt->bind_param("si", $informacion, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al guardar: ' . $stmt->error]);
    }
    exit;
}

if ($action === 'guardar_reposicion') {
    $stmt = $conn->prepare("UPDATE CAT_REPOSICION SET informacion = ? WHERE ID_REPOSICION = ?");
    $stmt->bind_param("si", $informacion, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al guardar: ' . $stmt->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Acción no válida.']);
