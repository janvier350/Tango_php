<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset("utf8");

$action = $_POST['action'] ?? '';

if ($action === 'check') {
    $razon = strtoupper(trim($_POST['razon_social'] ?? ''));
    if ($razon === '') { echo json_encode(['exists' => false]); exit; }
    $stmt = $conn->prepare("SELECT ID_BENEFICIARIO FROM BENEFICIARIO WHERE UPPER(RAZON_SOCIAL) = ?");
    $stmt->bind_param("s", $razon);
    $stmt->execute();
    echo json_encode(['exists' => $stmt->get_result()->num_rows > 0]);
    exit;
}

if ($action === 'crear') {
    $razon = strtoupper(trim($_POST['razon_social'] ?? ''));
    $tipo  = in_array($_POST['tipo'] ?? '', ['I', '0']) ? $_POST['tipo'] : '0';

    if ($razon === '') {
        echo json_encode(['success' => false, 'msg' => 'La razón social es obligatoria.']); exit;
    }

    $stmt = $conn->prepare("SELECT ID_BENEFICIARIO FROM BENEFICIARIO WHERE UPPER(RAZON_SOCIAL) = ?");
    $stmt->bind_param("s", $razon);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'msg' => 'Ya existe un beneficiario con esa razón social.']); exit;
    }

    $id_usuario = $_SESSION['user_id'];
    $fecha      = date('Y-m-d');
    $estado     = 'A';

    $ins = $conn->prepare("INSERT INTO BENEFICIARIO (RAZON_SOCIAL, ESTADO, ID_USUARIO_CREA, FECHA_CREA, TIPO) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("ssiss", $razon, $estado, $id_usuario, $fecha, $tipo);

    if ($ins->execute()) {
        echo json_encode(['success' => true, 'razon_social' => $razon, 'tipo' => $tipo]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al guardar: ' . $ins->error]);
    }
    exit;
}

if ($action === 'search') {
    // Búsqueda bajo demanda para Select2 (evita volcar toda la tabla en la página)
    $q    = trim($_POST['q'] ?? $_GET['q'] ?? '');
    $tipo = $_POST['tipo'] ?? $_GET['tipo'] ?? '';
    $like = '%' . $q . '%';
    if ($tipo === 'I') {
        $stmt = $conn->prepare("SELECT RAZON_SOCIAL FROM BENEFICIARIO WHERE TIPO='I' AND RAZON_SOCIAL LIKE ? ORDER BY RAZON_SOCIAL ASC LIMIT 40");
    } else {
        $stmt = $conn->prepare("SELECT RAZON_SOCIAL FROM BENEFICIARIO WHERE RAZON_SOCIAL LIKE ? ORDER BY RAZON_SOCIAL ASC LIMIT 40");
    }
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = ['id' => $row['RAZON_SOCIAL'], 'text' => $row['RAZON_SOCIAL']];
    }
    echo json_encode(['results' => $out]);
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Acción no válida.']);
