<?php
// Guardado del glosario para el acceso temporal por token (sin sesión).
require_once 'config.php';
date_default_timezone_set('America/Guayaquil');

header('Content-Type: application/json; charset=utf-8');

// Validar token en cada guardado
$token = trim($_POST['token'] ?? '');
$token_valido = false;

if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $stmt_tok = $conn->prepare("SELECT fecha_expira, activo FROM glosario_tokens WHERE token = ?");
    if ($stmt_tok) {
        $stmt_tok->bind_param("s", $token);
        $stmt_tok->execute();
        $row = $stmt_tok->get_result()->fetch_assoc();
        if ($row && $row['activo'] == 1 && $row['fecha_expira'] >= date('Y-m-d H:i:s')) {
            $token_valido = true;
        }
    }
}

if (!$token_valido) {
    echo json_encode(['success' => false, 'msg' => 'El enlace temporal ya no es válido. Solicita uno nuevo.']);
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
