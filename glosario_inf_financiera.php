<?php
require_once 'config.php';
require_once 'auth.php';
verificar_auth();

$id_rol = $_SESSION['user_rol'];
if (!in_array($id_rol, [2, 3, 4])) {
    header("Location: movimientos.php");
    exit;
}
$puede_editar = in_array($id_rol, [3, 4]);

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
