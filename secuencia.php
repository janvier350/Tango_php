<?php
/*
 * Secuencia por caja (número de presentación, NO altera el id real).
 *
 * Devuelve un mapa [id_movimiento => secuencia] para una oficina, numerando
 * TODOS sus movimientos (incluidos los anulados) en orden cronológico
 * (fecha ASC, id ASC). Así cada caja tiene su propia secuencia 1..N sin saltos,
 * independiente del id global de la base.
 *
 * Como los movimientos nunca se borran físicamente (anular = ESTADO='I'), la
 * secuencia queda continua; un número faltante indicaría un borrado real.
 */
function mapa_secuencia_caja($conn, $id_oficina) {
    static $cache = [];
    $id_oficina = (int)$id_oficina;
    if (isset($cache[$id_oficina])) return $cache[$id_oficina];

    $map  = [];
    $stmt = $conn->prepare("SELECT id FROM movimientos WHERE ID_OFICINA = ? ORDER BY fecha ASC, id ASC");
    $stmt->bind_param("i", $id_oficina);
    $stmt->execute();
    $res = $stmt->get_result();
    $i = 1;
    while ($r = $res->fetch_assoc()) { $map[(int)$r['id']] = $i++; }
    $stmt->close();

    $cache[$id_oficina] = $map;
    return $map;
}
