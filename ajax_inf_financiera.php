<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

header('Content-Type: application/json; charset=utf-8');
$conn->set_charset("utf8");

$action = $_POST['action'] ?? '';

// Devuelve lista de categorías para el select del modal
if ($action === 'categorias') {
    $res = $conn->query("SELECT id, nombre FROM cat_reposiciones ORDER BY nombre ASC");
    $cats = [];
    while ($r = $res->fetch_assoc()) $cats[] = $r;
    echo json_encode($cats);
    exit;
}

// Verifica si una categoría ya existe
if ($action === 'check_categoria') {
    $nombre = strtoupper(trim($_POST['nombre'] ?? ''));
    if ($nombre === '') { echo json_encode(['exists' => false]); exit; }
    $stmt = $conn->prepare("SELECT id FROM cat_reposiciones WHERE UPPER(nombre) = ?");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    echo json_encode(['exists' => $stmt->get_result()->num_rows > 0]);
    exit;
}

// Verifica si una reposición ya existe dentro de la categoría
if ($action === 'check_reposicion') {
    $reposicion  = strtoupper(trim($_POST['reposicion'] ?? ''));
    $id_cat      = intval($_POST['id_cat'] ?? 0);
    if ($reposicion === '' || $id_cat === 0) { echo json_encode(['exists' => false]); exit; }
    $stmt = $conn->prepare("SELECT ID_REPOSICION FROM CAT_REPOSICION WHERE UPPER(REPOSICION) = ? AND ID_CAT_REPOCICIONES = ?");
    $stmt->bind_param("si", $reposicion, $id_cat);
    $stmt->execute();
    echo json_encode(['exists' => $stmt->get_result()->num_rows > 0]);
    exit;
}

// Crear categoría y/o reposición
if ($action === 'crear') {
    $reposicion     = strtoupper(trim($_POST['reposicion']     ?? ''));
    $nueva_cat      = strtoupper(trim($_POST['nueva_cat']      ?? ''));
    $id_cat_existente = intval($_POST['id_cat_existente'] ?? 0);
    $usar_nueva_cat = ($_POST['usar_nueva_cat'] ?? '0') === '1';

    if ($reposicion === '') {
        echo json_encode(['success' => false, 'msg' => 'El nombre de la inf. financiera es obligatorio.']); exit;
    }

    // Determinar la categoría final
    if ($usar_nueva_cat) {
        if ($nueva_cat === '') {
            echo json_encode(['success' => false, 'msg' => 'Ingresa el nombre de la nueva categoría.']); exit;
        }
        // Verificar que la categoría nueva no exista
        $stmt = $conn->prepare("SELECT id FROM cat_reposiciones WHERE UPPER(nombre) = ?");
        $stmt->bind_param("s", $nueva_cat);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $id_cat = $row['id'];
        } else {
            $ins_cat = $conn->prepare("INSERT INTO cat_reposiciones (nombre) VALUES (?)");
            $ins_cat->bind_param("s", $nueva_cat);
            if (!$ins_cat->execute()) {
                echo json_encode(['success' => false, 'msg' => 'Error al crear la categoría: ' . $ins_cat->error]); exit;
            }
            $id_cat = $conn->insert_id;
        }
        $nombre_cat = $nueva_cat;
    } else {
        if ($id_cat_existente === 0) {
            echo json_encode(['success' => false, 'msg' => 'Selecciona una categoría.']); exit;
        }
        $id_cat = $id_cat_existente;
        $res = $conn->query("SELECT nombre FROM cat_reposiciones WHERE id = $id_cat");
        $nombre_cat = $res->fetch_assoc()['nombre'] ?? '';
    }

    // Verificar que la reposición no exista en esa categoría
    $stmt = $conn->prepare("SELECT ID_REPOSICION FROM CAT_REPOSICION WHERE UPPER(REPOSICION) = ? AND ID_CAT_REPOCICIONES = ?");
    $stmt->bind_param("si", $reposicion, $id_cat);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'msg' => 'Ya existe esa inf. financiera en la categoría seleccionada.']); exit;
    }

    $ins = $conn->prepare("INSERT INTO CAT_REPOSICION (REPOSICION, ID_CAT_REPOCICIONES) VALUES (?, ?)");
    $ins->bind_param("si", $reposicion, $id_cat);
    if ($ins->execute()) {
        $new_id = $conn->insert_id;
        echo json_encode([
            'success'    => true,
            'id'         => $new_id,
            'reposicion' => $reposicion,
            'categoria'  => $nombre_cat,
            'label'      => $nombre_cat . ' / ' . $reposicion
        ]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Error al guardar: ' . $ins->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Acción no válida.']);
