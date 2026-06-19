<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset("utf8");

$action = $_POST['action'] ?? '';

// Lista completa del catálogo de Inf. Financiera (categoria - detalle)
if ($action === 'listar') {
    $res = $conn->query("SELECT a.nombre AS categoria, b.REPOSICION AS detalle, b.ID_REPOSICION
                          FROM cat_reposiciones a
                          INNER JOIN CAT_REPOSICION b ON a.id = b.ID_CAT_REPOCICIONES
                          ORDER BY a.nombre ASC, b.REPOSICION ASC");
    $items = [];
    while ($r = $res->fetch_assoc()) {
        $items[] = ['id' => $r['ID_REPOSICION'], 'label' => $r['categoria'] . ' - ' . $r['detalle']];
    }
    echo json_encode($items);
    exit;
}

// Actualiza la Inf. Financiera de un movimiento existente
if ($action === 'actualizar') {
    $id_rol = $_SESSION['user_rol'] ?? 0;
    if ($id_rol != 3 && $id_rol != 4) {
        echo json_encode(['success' => false, 'msg' => 'No tienes permisos para realizar esta acción.']); exit;
    }

    $id_movimiento = intval($_POST['id_movimiento'] ?? 0);
    $id_reposicion = intval($_POST['id_reposicion'] ?? 0);
    if ($id_movimiento === 0 || $id_reposicion === 0) {
        echo json_encode(['success' => false, 'msg' => 'Datos incompletos.']); exit;
    }

    $stmt = $conn->prepare("SELECT ESTADO, ID_USUARIO_REVISA FROM movimientos WHERE id = ?");
    $stmt->bind_param("i", $id_movimiento);
    $stmt->execute();
    $mov = $stmt->get_result()->fetch_assoc();

    if (!$mov) {
        echo json_encode(['success' => false, 'msg' => 'Movimiento no encontrado.']); exit;
    }
    if ($mov['ESTADO'] === 'I') {
        echo json_encode(['success' => false, 'msg' => 'No se puede modificar: el movimiento está anulado.']); exit;
    }
    if ($mov['ID_USUARIO_REVISA'] > 0) {
        echo json_encode(['success' => false, 'msg' => 'No se puede modificar: el movimiento ya fue validado.']); exit;
    }

    $upd = $conn->prepare("UPDATE movimientos SET inf_fin = ? WHERE id = ?");
    $upd->bind_param("ii", $id_reposicion, $id_movimiento);
    if ($upd->execute()) {
        $stmt2 = $conn->prepare("SELECT a.nombre AS categoria, b.REPOSICION AS detalle
                                  FROM cat_reposiciones a
                                  INNER JOIN CAT_REPOSICION b ON a.id = b.ID_CAT_REPOCICIONES
                                  WHERE b.ID_REPOSICION = ?");
        $stmt2->bind_param("i", $id_reposicion);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $label = $row ? $row['categoria'] . ' / ' . $row['detalle'] : '';
        echo json_encode(['success' => true, 'label' => $label]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al actualizar: ' . $upd->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Acción no válida.']);
