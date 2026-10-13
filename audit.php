<?php
/*
 * Log de auditoría: registro de acciones (quién, cuándo, qué módulo, qué acción,
 * sobre qué registro y con qué detalle). Similar al de HealthSchedule.
 *
 * Uso:  require_once 'audit.php';
 *       registrar_auditoria($conn, 'Movimientos', 'CREO', $id, 'Concepto X - $100');
 *
 * La tabla 'auditoria' se crea automáticamente la primera vez si no existe
 * (si el usuario de BD no tuviera permiso CREATE, el registro simplemente se
 * omite sin romper la app; en ese caso se crea con el SQL del chat).
 */

function auditoria_map_rol($rol) {
    $m = [1 => 'ADMINISTRADOR', 2 => 'RECEPCION', 3 => 'CEO', 4 => 'ADMINISTRACION'];
    return $m[(int)$rol] ?? ('ROL ' . $rol);
}

function auditoria_crear_tabla($conn) {
    @$conn->query("CREATE TABLE IF NOT EXISTS auditoria (
        id BIGINT NOT NULL AUTO_INCREMENT,
        fecha_hora DATETIME NOT NULL,
        id_usuario INT NULL,
        usuario VARCHAR(120) NULL,
        rol VARCHAR(60) NULL,
        oficina VARCHAR(120) NULL,
        modulo VARCHAR(60) NOT NULL,
        accion VARCHAR(40) NOT NULL,
        id_registro VARCHAR(60) NULL,
        detalle TEXT NULL,
        ip VARCHAR(60) NULL,
        user_agent VARCHAR(400) NULL,
        PRIMARY KEY (id),
        KEY idx_fecha (fecha_hora),
        KEY idx_usuario (id_usuario),
        KEY idx_modulo (modulo),
        KEY idx_accion (accion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Agregar la columna user_agent si la tabla ya existía sin ella.
    $r = @$conn->query("SHOW COLUMNS FROM auditoria LIKE 'user_agent'");
    if ($r && $r->num_rows === 0) {
        @$conn->query("ALTER TABLE auditoria ADD COLUMN user_agent VARCHAR(400) NULL");
    }
}

// Parsea el User-Agent a Dispositivo / Sistema Operativo / Navegador.
function auditoria_parse_ua($ua) {
    $ua = (string)$ua;
    if ($ua === '') return ['dispositivo' => '—', 'so' => '—', 'navegador' => '—'];
    $d = 'Computadora'; $so = '—'; $nav = '—';
    if (preg_match('/iPad|Tablet/i', $ua)) $d = 'Tablet';
    elseif (preg_match('/Mobile|iPhone|iPod|Android/i', $ua) && preg_match('/Mobile/i', $ua)) $d = 'Celular';
    elseif (preg_match('/iPhone|iPod/i', $ua)) $d = 'Celular';

    if (preg_match('/Windows NT/i', $ua)) $so = 'Windows';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $so = 'iOS';
    elseif (preg_match('/Android/i', $ua)) $so = 'Android';
    elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) $so = 'macOS';
    elseif (preg_match('/Linux/i', $ua)) $so = 'Linux';

    if (preg_match('/Edg\//i', $ua)) $nav = 'Edge';
    elseif (preg_match('/OPR\/|Opera/i', $ua)) $nav = 'Opera';
    elseif (preg_match('/Firefox\//i', $ua)) $nav = 'Firefox';
    elseif (preg_match('/Chrome\//i', $ua)) $nav = 'Chrome';
    elseif (preg_match('/Safari\//i', $ua)) $nav = 'Safari';
    return ['dispositivo' => $d, 'so' => $so, 'navegador' => $nav];
}

/**
 * Registra una acción en el log. Nunca interrumpe el flujo principal:
 * si algo falla (tabla inexistente, sin permisos), se omite silenciosamente.
 *
 * @param mysqli $conn
 * @param string $modulo      Ej: 'Movimientos', 'Sesion', 'Empresa', 'Arqueo'
 * @param string $accion      Ej: 'CREO', 'EDITO', 'ANULO', 'APROBO', 'LOGIN', 'LOGOUT'
 * @param mixed  $id_registro Id del registro afectado (opcional)
 * @param string $detalle     Descripción legible del cambio (opcional)
 */
function registrar_auditoria($conn, $modulo, $accion, $id_registro = null, $detalle = '') {
    if (!$conn) return;
    static $intento_crear = false;
    // Asegurar hora de Ecuador aunque la página que llama no la haya fijado
    // (ej. login.php), para que la fecha/hora del log sea correcta.
    date_default_timezone_set('America/Guayaquil');

    $fecha      = date('Y-m-d H:i:s');
    $id_usuario = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $usuario    = $_SESSION['user_name'] ?? '';
    $rol        = isset($_SESSION['user_rol']) ? auditoria_map_rol($_SESSION['user_rol']) : '';
    $oficina    = $_SESSION['oficina'] ?? '';
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua         = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
    $idr        = ($id_registro !== null && $id_registro !== '') ? (string)$id_registro : null;
    $detalle    = (string)$detalle;

    $sql = "INSERT INTO auditoria
            (fecha_hora,id_usuario,usuario,rol,oficina,modulo,accion,id_registro,detalle,ip,user_agent)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = @$conn->prepare($sql);
    if (!$stmt && !$intento_crear) {
        // Probablemente la tabla/columna no existe: crearla/ajustarla y reintentar.
        $intento_crear = true;
        auditoria_crear_tabla($conn);
        $stmt = @$conn->prepare($sql);
    }
    if (!$stmt) return;

    $stmt->bind_param("sisssssssss",
        $fecha, $id_usuario, $usuario, $rol, $oficina,
        $modulo, $accion, $idr, $detalle, $ip, $ua);
    @$stmt->execute();
    $stmt->close();
}
