<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset("utf8");

$action = $_POST['action'] ?? '';

if ($action === 'check') {
    $nombre = strtoupper(trim($_POST['proyecto'] ?? ''));
    if ($nombre === '') { echo json_encode(['exists' => false]); exit; }
    $stmt = $conn->prepare("SELECT ID_PROYECTO FROM PROYECTOS WHERE UPPER(PROYECTO) = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    echo json_encode(['exists' => $stmt->get_result()->num_rows > 0]);
    exit;
}

if ($action === 'crear') {
    $nombre = strtoupper(trim($_POST['proyecto'] ?? ''));

    if ($nombre === '') {
        echo json_encode(['success' => false, 'msg' => 'El nombre del proyecto es obligatorio.']); exit;
    }

    $stmt = $conn->prepare("SELECT ID_PROYECTO FROM PROYECTOS WHERE UPPER(PROYECTO) = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'msg' => 'Ya existe un proyecto con ese nombre.']); exit;
    }

    $ins = $conn->prepare("INSERT INTO PROYECTOS (PROYECTO) VALUES (?)");
    $ins->bind_param("s", $nombre);

    if ($ins->execute()) {
        $new_id = $conn->insert_id;
        echo json_encode(['success' => true, 'id' => $new_id, 'proyecto' => $nombre]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al guardar: ' . $ins->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Acción no válida.']);
