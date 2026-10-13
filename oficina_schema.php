<?php
/*
 * Esquema de oficinas: separa "no se suma al total" (ES_INDEPENDIENTE) de
 * "usa formulario simplificado tipo Mensajería" (FORM_SIMPLE).
 *
 * Antes, ES_INDEPENDIENTE hacía ambas cosas. Con FORM_SIMPLE una caja puede ser
 * independiente (no suma) pero usar el formulario completo (como 401/403).
 */

// Crea la columna FORM_SIMPLE si no existe y migra las cajas que hoy son
// independientes para que conserven el formulario simplificado (Mensajería).
function asegurar_form_simple($conn) {
    static $done = false;
    if ($done) return;
    $done = true;

    $r = @$conn->query("SHOW COLUMNS FROM CTR_OFICINA LIKE 'FORM_SIMPLE'");
    $existe = ($r && $r->num_rows > 0);
    if (!$existe) {
        @$conn->query("ALTER TABLE CTR_OFICINA ADD COLUMN FORM_SIMPLE TINYINT(1) NOT NULL DEFAULT 0");
        // Las cajas independientes actuales usaban el formulario simplificado:
        // conservar ese comportamiento (afecta solo a Mensajería hoy).
        $r2 = @$conn->query("SHOW COLUMNS FROM CTR_OFICINA LIKE 'ES_INDEPENDIENTE'");
        if ($r2 && $r2->num_rows > 0) {
            @$conn->query("UPDATE CTR_OFICINA SET FORM_SIMPLE = 1 WHERE ES_INDEPENDIENTE = 1");
        }
    }
}

// ¿La oficina usa el formulario simplificado (tipo Mensajería)?
// Prefiere FORM_SIMPLE; si la columna no existe aún, cae a ES_INDEPENDIENTE
// (comportamiento previo) para no romper nada durante el despliegue.
function oficina_usa_form_simple($conn, $id_oficina) {
    $id_oficina = (int)$id_oficina;
    if ($id_oficina <= 0) return false;

    $col = null;
    $r = @$conn->query("SHOW COLUMNS FROM CTR_OFICINA LIKE 'FORM_SIMPLE'");
    if ($r && $r->num_rows > 0) {
        $col = 'FORM_SIMPLE';
    } else {
        $r2 = @$conn->query("SHOW COLUMNS FROM CTR_OFICINA LIKE 'ES_INDEPENDIENTE'");
        if ($r2 && $r2->num_rows > 0) $col = 'ES_INDEPENDIENTE';
    }
    if (!$col) return false;

    $res = @$conn->query("SELECT $col AS v FROM CTR_OFICINA WHERE ID_OFICINA = $id_oficina");
    return $res ? (bool)($res->fetch_assoc()['v'] ?? 0) : false;
}
