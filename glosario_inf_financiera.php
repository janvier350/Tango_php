<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
date_default_timezone_set('America/Guayaquil');

$id_rol = $_SESSION['user_rol'];
if (!in_array($id_rol, [2, 3, 4])) {
    header("Location: movimientos.php");
    exit;
}
$puede_editar = in_array($id_rol, [3, 4]);

// ── Enlaces temporales (solo CEO / Administración) ───────────────────
$msg_token = ''; $tipo_msg_token = '';

if ($puede_editar && isset($_POST['crear_token'])) {
    $desc      = trim($_POST['token_desc'] ?? '');
    $fecha_lim = $_POST['token_fecha'] ?? '';
    if ($fecha_lim === '' || $fecha_lim < date('Y-m-d')) {
        $msg_token = 'Debes indicar una fecha límite válida (hoy o posterior).';
        $tipo_msg_token = 'danger';
    } else {
        $token_nuevo = bin2hex(random_bytes(16));
        $expira      = $fecha_lim . ' 23:59:59';
        $uid         = $_SESSION['user_id'];
        $stmt_tok = $conn->prepare("INSERT INTO glosario_tokens (token, descripcion, fecha_expira, creado_por) VALUES (?, ?, ?, ?)");
        if ($stmt_tok) {
            $stmt_tok->bind_param("sssi", $token_nuevo, $desc, $expira, $uid);
            if ($stmt_tok->execute()) {
                $msg_token = 'Enlace generado correctamente. Cópialo de la lista y compártelo.';
                $tipo_msg_token = 'success';
            } else {
                $msg_token = 'Error al generar: ' . htmlspecialchars($stmt_tok->error);
                $tipo_msg_token = 'danger';
            }
        } else {
            $msg_token = 'Falta la tabla <code>glosario_tokens</code> en la base de datos. Ejecuta el SQL indicado.';
            $tipo_msg_token = 'warning';
        }
    }
}

if ($puede_editar && isset($_GET['anular_token'])) {
    $id_tok = intval($_GET['anular_token']);
    $stmt_an = $conn->prepare("UPDATE glosario_tokens SET activo = 0 WHERE id = ?");
    if ($stmt_an) {
        $stmt_an->bind_param("i", $id_tok);
        $stmt_an->execute();
        $msg_token = 'Enlace anulado.';
        $tipo_msg_token = 'success';
    }
}

// Lista de enlaces (si la tabla aún no existe, $tokens queda en null y se muestra aviso)
$tokens = null;
if ($puede_editar) {
    $res_tok = $conn->query("SELECT id, token, descripcion, fecha_expira, activo, fecha_creacion
                             FROM glosario_tokens ORDER BY fecha_creacion DESC LIMIT 20");
    if ($res_tok) { $tokens = $res_tok->fetch_all(MYSQLI_ASSOC); }
}

// URL base para armar el enlace compartible
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/glosario_temporal.php';

$res = $conn->query("
    SELECT c.id AS cat_id, c.nombre AS cat_nombre, c.informacion AS cat_info,
           r.ID_REPOSICION AS rep_id, r.REPOSICION AS rep_nombre, r.informacion AS rep_info
    FROM cat_reposiciones c
    LEFT JOIN CAT_REPOSICION r ON r.ID_CAT_REPOCICIONES = c.id
    ORDER BY c.nombre ASC, r.REPOSICION ASC
");

$categorias = [];
while ($row = $res->fetch_assoc()) {
    $cid = $row['cat_id'];
    if (!isset($categorias[$cid])) {
        $categorias[$cid] = [
            'nombre'      => $row['cat_nombre'],
            'informacion' => $row['cat_info'],
            'items'       => [],
        ];
    }
    if ($row['rep_id']) {
        $categorias[$cid]['items'][] = [
            'id'          => $row['rep_id'],
            'nombre'      => $row['rep_nombre'],
            'informacion' => $row['rep_info'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>Glosario de Inf. Financiera | TANGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .glosario-input { font-size: 0.85rem; }
        .glosario-row .btn-guardar-glosario { transition: opacity .15s; }
        .cat-header { background: #2c3e50; color: #fff; }
    </style>
</head>
<body class="bg-light">
<?php $id_rol == 4 ? include 'navbar_control.php' : include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Glosario de Inf. Financiera</h4>
        <div style="width: 320px;">
            <input type="text" id="glosario_buscar" class="form-control form-control-sm"
                   placeholder="Buscar categoría o concepto...">
        </div>
    </div>
    <?php if ($puede_editar): ?>
    <p class="text-muted small">
        Agrega una breve descripción a cada categoría y concepto para que cualquier persona sepa a qué corresponde
        (ej: <code>CXC-XP</code> &rarr; <em>Cuentas por cobrar Xavier Parrales</em>).
        Los cambios se guardan automáticamente al presionar el botón de guardar de cada fila.
    </p>
    <?php else: ?>
    <p class="text-muted small">
        Consulta a qué corresponde cada categoría y concepto de Inf. Financiera
        (ej: <code>CXC-XP</code> &rarr; <em>Cuentas por cobrar Xavier Parrales</em>).
    </p>
    <?php endif; ?>

    <?php if ($puede_editar): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold d-flex align-items-center"
             data-bs-toggle="collapse" data-bs-target="#panelEnlaces" style="cursor: pointer;">
            <i class="bi bi-link-45deg me-2"></i> Enlaces temporales para llenar el glosario
            <i class="bi bi-chevron-down ms-auto"></i>
        </div>
        <div id="panelEnlaces" class="collapse <?php echo ($msg_token || !empty($_POST['crear_token'])) ? 'show' : ''; ?>">
            <div class="card-body">

                <?php if ($msg_token): ?>
                <div class="alert alert-<?php echo $tipo_msg_token; ?> alert-dismissible fade show py-2 small">
                    <?php echo $msg_token; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($tokens === null): ?>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Para activar esta función, ejecuta primero en phpMyAdmin el SQL de creación
                    de la tabla <code>glosario_tokens</code>.
                </div>
                <?php else: ?>

                <p class="text-muted small mb-2">
                    Genera un enlace para que una persona <strong>sin usuario en el sistema</strong> pueda llenar
                    el glosario hasta la fecha límite. Solo verá esta página, nada más del sistema.
                    Puedes anular un enlace en cualquier momento.
                </p>

                <form method="POST" class="row g-2 align-items-end mb-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold mb-1">¿Para quién es? (referencia)</label>
                        <input type="text" name="token_desc" class="form-control form-control-sm"
                               placeholder="Ej: Contadora externa - María" maxlength="255">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Válido hasta</label>
                        <input type="date" name="token_fecha" class="form-control form-control-sm"
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <button name="crear_token" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-plus-circle me-1"></i> Generar enlace
                        </button>
                    </div>
                </form>

                <?php if (!empty($tokens)): ?>
                <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Referencia</th>
                            <th>Válido hasta</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center" style="width: 180px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $tk):
                            $vencido = ($tk['fecha_expira'] < date('Y-m-d H:i:s'));
                            if (!$tk['activo'])   { $estado = '<span class="badge bg-secondary">Anulado</span>'; }
                            elseif ($vencido)     { $estado = '<span class="badge bg-danger">Vencido</span>'; }
                            else                  { $estado = '<span class="badge bg-success">Activo</span>'; }
                            $url_tk = $base_url . '?token=' . $tk['token'];
                        ?>
                        <tr>
                            <td class="small"><?php echo htmlspecialchars($tk['descripcion'] ?: 'Sin referencia'); ?></td>
                            <td class="small"><?php echo date('d/m/Y', strtotime($tk['fecha_expira'])); ?></td>
                            <td class="text-center"><?php echo $estado; ?></td>
                            <td class="text-center">
                                <?php if ($tk['activo'] && !$vencido): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 me-1 btn-copiar-enlace"
                                        data-url="<?php echo htmlspecialchars($url_tk); ?>" title="Copiar enlace">
                                    <i class="bi bi-clipboard"></i> Copiar
                                </button>
                                <a href="?anular_token=<?php echo $tk['id']; ?>"
                                   class="btn btn-outline-danger btn-sm py-0 px-2"
                                   onclick="return confirm('¿Anular este enlace? La persona ya no podrá acceder.');"
                                   title="Anular enlace">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php else: ?>
                <p class="text-muted small mb-0">Aún no has generado ningún enlace.</p>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="accordion" id="acordeonGlosario">
        <?php foreach ($categorias as $cid => $cat): ?>
        <div class="accordion-item glosario-row" data-tipo="categoria" data-id="<?php echo $cid; ?>">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-bold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#cat<?php echo $cid; ?>">
                    <?php echo htmlspecialchars($cat['nombre']); ?>
                    <span class="badge bg-secondary ms-2"><?php echo count($cat['items']); ?></span>
                </button>
            </h2>
            <div id="cat<?php echo $cid; ?>" class="accordion-collapse collapse">
                <div class="accordion-body">

                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text">Descripción de la categoría</span>
                        <input type="text" class="form-control glosario-input"
                               value="<?php echo htmlspecialchars($cat['informacion'] ?? ''); ?>"
                               placeholder="¿Para qué se usa esta categoría?"
                               <?php echo $puede_editar ? '' : 'readonly'; ?>>
                        <?php if ($puede_editar): ?>
                        <button class="btn btn-success btn-guardar-glosario d-none" type="button">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($cat['items'])): ?>
                        <p class="text-muted small mb-0">Esta categoría no tiene conceptos registrados.</p>
                    <?php else: ?>
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 220px;">Concepto</th>
                                <th>Descripción</th>
                                <?php if ($puede_editar): ?><th style="width: 40px;"></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cat['items'] as $item): ?>
                            <tr class="glosario-row" data-tipo="reposicion" data-id="<?php echo $item['id']; ?>">
                                <td class="fw-semibold small"><?php echo htmlspecialchars($item['nombre']); ?></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm glosario-input"
                                           value="<?php echo htmlspecialchars($item['informacion'] ?? ''); ?>"
                                           placeholder="¿A qué corresponde este concepto?"
                                           <?php echo $puede_editar ? '' : 'readonly'; ?>>
                                </td>
                                <?php if ($puede_editar): ?>
                                <td>
                                    <button class="btn btn-sm btn-success btn-guardar-glosario d-none" type="button">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($categorias)): ?>
        <p class="text-muted text-center py-4">No hay categorías registradas.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {

    $('.glosario-input').on('input', function() {
        $(this).removeClass('is-valid')
            .closest('.glosario-row')
            .find('.btn-guardar-glosario')
            .removeClass('d-none');
    });

    $('.btn-guardar-glosario').on('click', function() {
        var $btn   = $(this);
        var $row   = $btn.closest('.glosario-row');
        var tipo   = $row.data('tipo');
        var id     = $row.data('id');
        var $input = $row.find('.glosario-input');
        var info   = $input.val().trim();
        var icono  = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.post('ajax_glosario_inf_fin.php', {
            action: tipo === 'categoria' ? 'guardar_categoria' : 'guardar_reposicion',
            id: id,
            informacion: info
        }, function(res) {
            $btn.prop('disabled', false).html(icono);
            if (res.success) {
                $btn.addClass('d-none');
                $input.addClass('is-valid');
                setTimeout(function() { $input.removeClass('is-valid'); }, 1500);
            } else {
                alert(res.msg || 'Error al guardar.');
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html(icono);
            alert('Error de conexión. Intenta nuevamente.');
        });
    });

    // Evitar que el clic en el input de categoría colapse el accordion
    $('.accordion-body').on('click', function(e) { e.stopPropagation(); });

    // Copiar enlace temporal al portapapeles
    $('.btn-copiar-enlace').on('click', function() {
        var $btn = $(this);
        var url  = $btn.data('url');
        var restaurar = function() {
            setTimeout(function() { $btn.html('<i class="bi bi-clipboard"></i> Copiar'); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                $btn.html('<i class="bi bi-check-lg"></i> Copiado');
                restaurar();
            });
        } else {
            window.prompt('Copia el enlace:', url);
        }
    });

    $('#glosario_buscar').on('input', function() {
        var q = $(this).val().toLowerCase();
        if (q === '') {
            $('#acordeonGlosario .accordion-item').show();
            $('#acordeonGlosario tbody tr').show();
            return;
        }
        $('#acordeonGlosario .accordion-item').each(function() {
            var $item = $(this);
            var catMatch = $item.find('.accordion-button').text().toLowerCase().indexOf(q) !== -1;
            var anyRowMatch = false;
            $item.find('tbody tr').each(function() {
                var match = $(this).text().toLowerCase().indexOf(q) !== -1;
                $(this).toggle(match);
                if (match) anyRowMatch = true;
            });
            $item.toggle(catMatch || anyRowMatch);
            if (anyRowMatch && !catMatch) {
                new bootstrap.Collapse($item.find('.accordion-collapse')[0], { show: true });
            }
        });
    });
});
</script>
</body>
</html>
