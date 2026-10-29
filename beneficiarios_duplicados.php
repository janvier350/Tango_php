<?php
date_default_timezone_set('America/Guayaquil');
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
$conn->set_charset("utf8");

// Solo Administrador (1), CEO (3) y Administración (4)
$id_rol = intval($_SESSION["user_rol"] ?? 0);
if (!in_array($id_rol, [1, 3, 4])) { header("Location: index.php"); exit; }

require_once 'audit.php';

function benef_norm($s) { return preg_replace('/\s+/', ' ', mb_strtoupper(trim((string)$s))); }

$msg = ''; $tipo_msg = '';

// --- Fusionar un grupo de duplicados (conservar el id mas antiguo) ---
if (isset($_POST['fusionar'])) {
    $key = benef_norm($_POST['key'] ?? '');
    if ($key !== '') {
        $stmt = $conn->prepare("SELECT ID_BENEFICIARIO, RAZON_SOCIAL, TIPO
                                FROM BENEFICIARIO
                                WHERE UPPER(TRIM(RAZON_SOCIAL)) = ?
                                ORDER BY ID_BENEFICIARIO ASC");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($filas) > 1) {
            $survivor = (int)$filas[0]['ID_BENEFICIARIO'];
            $nombre   = $filas[0]['RAZON_SOCIAL'];
            $any_I    = false;
            $borrar   = [];
            foreach ($filas as $f) {
                if ($f['TIPO'] === 'I') $any_I = true;
                if ((int)$f['ID_BENEFICIARIO'] !== $survivor) $borrar[] = (int)$f['ID_BENEFICIARIO'];
            }
            // Conservar la condicion de intermediario si alguna fila la tenia.
            if ($any_I) {
                $conn->query("UPDATE BENEFICIARIO SET TIPO='I' WHERE ID_BENEFICIARIO = $survivor");
            }
            if (!empty($borrar)) {
                $ids = implode(',', array_map('intval', $borrar));
                $conn->query("DELETE FROM BENEFICIARIO WHERE ID_BENEFICIARIO IN ($ids)");
            }
            registrar_auditoria($conn, 'Beneficiario', 'ELIMINO', $survivor,
                "Fusionó duplicados de '$nombre': conservó id $survivor, borró ids " . implode(',', $borrar));
            $msg = "Listo: se conservó <strong>id $survivor</strong> de \"" . htmlspecialchars($nombre) . "\" y se borraron " . count($borrar) . " duplicado(s). Los movimientos no se afectan (usan el nombre).";
            $tipo_msg = 'success';
        } else {
            $msg = 'Ese beneficiario ya no tiene duplicados.'; $tipo_msg = 'info';
        }
    }
}

// --- Lista de grupos duplicados ---
$dups = $conn->query("
    SELECT UPPER(TRIM(RAZON_SOCIAL)) AS norm,
           COUNT(*) AS n,
           GROUP_CONCAT(ID_BENEFICIARIO ORDER BY ID_BENEFICIARIO) AS ids,
           GROUP_CONCAT(DISTINCT TIPO ORDER BY TIPO) AS tipos,
           MIN(ID_BENEFICIARIO) AS keep_id,
           MAX(TRIM(RAZON_SOCIAL)) AS muestra
    FROM BENEFICIARIO
    GROUP BY UPPER(TRIM(RAZON_SOCIAL))
    HAVING COUNT(*) > 1
    ORDER BY n DESC, norm ASC
");
$grupos = $dups ? $dups->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>TANGO | Beneficiarios duplicados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="estilos.css">
</head>
<body class="bg-light">
    <?php if ($id_rol == 4) { include 'navbar_control.php'; } else { include 'navbar.php'; } ?>

    <div class="container-fluid px-3 mt-3" style="max-width:1000px;">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-people-fill fs-3"></i>
            <div>
                <h4 class="fw-bold mb-0">Beneficiarios duplicados</h4>
                <p class="text-muted small mb-0">Nombres repetidos en el catálogo. Al fusionar se conserva el id más antiguo y se borran los demás; <strong>los movimientos no se afectan</strong> (usan el nombre, no el id).</p>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="alert alert-<?php echo $tipo_msg; ?> py-2 small"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if (empty($grupos)): ?>
        <div class="alert alert-success mt-3"><i class="bi bi-check-circle me-1"></i>No hay beneficiarios duplicados. 👌</div>
        <?php else: ?>
        <div class="bg-white shadow-sm p-2 table-responsive mt-2">
            <table class="table table-sm table-hover align-middle" style="font-size:.85rem;">
                <thead class="table-secondary">
                    <tr>
                        <th>Nombre</th>
                        <th class="text-center">Veces</th>
                        <th>IDs</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($grupos as $g): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($g['muestra']); ?></td>
                        <td class="text-center"><span class="badge bg-danger"><?php echo $g['n']; ?></span></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($g['ids']); ?></td>
                        <td class="text-center"><?php echo strpos($g['tipos'], 'I') !== false ? '<span class="badge bg-primary">Intermediario</span>' : '<span class="badge bg-secondary">Beneficiario</span>'; ?></td>
                        <td class="text-end">
                            <form method="POST" class="d-inline" onsubmit="return confirm('Conservar id <?php echo (int)$g['keep_id']; ?> y borrar los demás duplicados de este nombre?');">
                                <input type="hidden" name="key" value="<?php echo htmlspecialchars($g['norm']); ?>">
                                <button type="submit" name="fusionar" class="btn btn-sm btn-primary">
                                    <i class="bi bi-magnet me-1"></i>Dejar uno (id <?php echo (int)$g['keep_id']; ?>)
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Si una de las filas era "Intermediario", la que se conserva queda como Intermediario automáticamente.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
