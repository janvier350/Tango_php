<?php
/*
 * Multi-caja: un usuario puede operar más de una oficina.
 * La oficina PRINCIPAL sigue en usuarios.ID_CTR_OFICINA. Las ADICIONALES se
 * guardan en la tabla usuario_oficinas (muchos-a-muchos). La "caja activa" vive
 * en la sesión y se cambia con cambiar_caja.php.
 */
require_once __DIR__ . '/oficina_schema.php';

function asegurar_usuario_oficinas($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    @$conn->query("CREATE TABLE IF NOT EXISTS usuario_oficinas (
        ID_USUARIO INT NOT NULL,
        ID_OFICINA INT NOT NULL,
        PRIMARY KEY (ID_USUARIO, ID_OFICINA),
        KEY idx_uo_usuario (ID_USUARIO),
        KEY idx_uo_oficina (ID_OFICINA)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Oficinas que puede operar un usuario: la principal (usuarios.ID_CTR_OFICINA)
 * + las adicionales (usuario_oficinas). Devuelve filas con ID_OFICINA, OFICINA,
 * ES_INDEPENDIENTE, FORM_SIMPLE, ordenadas por nombre.
 */
function oficinas_de_usuario($conn, $id_usuario) {
    static $cache = [];
    $id_usuario = (int)$id_usuario;
    if ($id_usuario <= 0) return [];
    if (isset($cache[$id_usuario])) return $cache[$id_usuario];

    asegurar_usuario_oficinas($conn);
    asegurar_form_simple($conn); // asegura columna FORM_SIMPLE

    $sql = "SELECT o.ID_OFICINA, o.OFICINA,
                   COALESCE(o.ES_INDEPENDIENTE,0) AS ES_INDEPENDIENTE,
                   COALESCE(o.FORM_SIMPLE,0)      AS FORM_SIMPLE
            FROM CTR_OFICINA o
            WHERE o.ID_OFICINA = (SELECT ID_CTR_OFICINA FROM usuarios WHERE id = ?)
               OR o.ID_OFICINA IN (SELECT ID_OFICINA FROM usuario_oficinas WHERE ID_USUARIO = ?)
            ORDER BY o.OFICINA ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("ii", $id_usuario, $id_usuario);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $cache[$id_usuario] = $rows;
    return $rows;
}

// ¿El usuario puede operar esa oficina?
function usuario_puede_oficina($conn, $id_usuario, $id_oficina) {
    $id_oficina = (int)$id_oficina;
    foreach (oficinas_de_usuario($conn, $id_usuario) as $o) {
        if ((int)$o['ID_OFICINA'] === $id_oficina) return $o;
    }
    return null;
}
