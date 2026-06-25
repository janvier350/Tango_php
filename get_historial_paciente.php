<?php
session_start();
require_once("class/funciones.php");
require_once("class/conexionBD.php");
$conexion = conectarse();

if (!isset($_SESSION["rol"])) { http_response_code(403); exit; }

$idPaciente = (int)($_GET['id'] ?? 0);
if (!$idPaciente) { echo '<p class="text-danger p-3">ID no válido.</p>'; exit; }

// Datos del paciente
$stmtP = $conexion->prepare(
    "SELECT NOMBRES, APELLIDOS, CEDULA, TELEFONO, EMAIL, FECHANACIMIENTO, SEX, GENDER, FECHA_REGISTRO
     FROM AG_PACIENTE WHERE IDPACIENTE = ? LIMIT 1"
);
$stmtP->bind_param("i", $idPaciente);
$stmtP->execute();
$pac = $stmtP->get_result()->fetch_assoc();
$stmtP->close();

if (!$pac) { echo '<p class="text-danger p-3">Paciente no encontrado.</p>'; exit; }

// Talla más reciente registrada en atenciones
$stmtT = $conexion->prepare(
    "SELECT H.TALLA FROM AG_HISTORIAL H
     INNER JOIN AG_CITA C ON C.IDCITA = H.IDCITA
     WHERE C.IDPACIENTE = ? AND H.TALLA IS NOT NULL AND H.TALLA > 0
     ORDER BY H.FECHA_REGISTRO DESC LIMIT 1"
);
$stmtT->bind_param("i", $idPaciente);
$stmtT->execute();
$rowTalla = $stmtT->get_result()->fetch_assoc();
$stmtT->close();
$tallaActual = $rowTalla['TALLA'] ?? null;

// Edad calculada desde FECHANACIMIENTO
$edad = null;
$fn   = $pac['FECHANACIMIENTO'] ?? '';
if ($fn && $fn !== '0000-00-00' && $fn !== '') {
    try {
        $edad = (new DateTime($fn))->diff(new DateTime())->y;
    } catch (Exception $e) { $edad = null; }
}

// Todas las citas del paciente
$stmtC = $conexion->prepare(
    "SELECT C.IDCITA, C.FECHA_CITA, C.HORA_INICIO, C.HORA_FIN,
            C.ESTADO_CITA,
            TC.NOMBRES AS TIPO_CONSULTA,
            CONCAT(D.NOMBRES,' ',D.APELLIDOS) AS DOCTOR,
            H.IDHISTORIAL, H.PESO, H.TALLA, H.IMC, H.FECHA_REGISTRO
     FROM AG_CITA C
     LEFT JOIN AG_TIPOCONSULTA TC ON TC.IDTIPOCONSULTA = C.IDTIPOCONSULTA
     LEFT JOIN ADM_USUARIO D      ON D.IDADM_USUARIO   = C.IDDOCTOR
     LEFT JOIN AG_HISTORIAL H     ON H.IDCITA          = C.IDCITA
     WHERE C.IDPACIENTE = ? AND C.ESTADO = 'A'
     ORDER BY C.FECHA_CITA DESC, C.HORA_INICIO DESC"
);
$stmtC->bind_param("i", $idPaciente);
$stmtC->execute();
$citas = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtC->close();

// Contadores
$totalCitas = count($citas);
$atendidas  = 0; $pendientes = 0; $canceladas = 0;
foreach ($citas as $_c) {
    if ($_c['IDHISTORIAL']) $atendidas++;
    if ($_c['ESTADO_CITA'] === 'Pendiente') $pendientes++;
    if (in_array($_c['ESTADO_CITA'], ['Cancelada','Cancelado'])) $canceladas++;
}

// IMC promedio
$imcs    = array_filter(array_column($citas, 'IMC'));
$imcProm = count($imcs) ? number_format(array_sum($imcs) / count($imcs), 1) : null;

function badgeClass($est) {
    switch ($est) {
        case 'Confirmada':  return 'bg-success';
        case 'Pendiente':   return 'bg-warning text-dark';
        case 'Cancelada':
        case 'Cancelado':   return 'bg-danger';
        case 'A':           return 'badge-atendida';
        default:            return 'bg-secondary';
    }
}
function imcLabel($imc) {
    if ($imc < 18.5) return 'Bajo peso';
    if ($imc < 25)   return 'Normal';
    if ($imc < 30)   return 'Sobrepeso';
    return 'Obesidad';
}
function imcColor($imc) {
    if ($imc < 18.5) return '#0dcaf0';
    if ($imc < 25)   return '#198754';
    if ($imc < 30)   return '#ffc107';
    return '#dc3545';
}
?>
<style>
    .badge-atendida { background:#6f42c1; color:#fff; }
    .info-label { font-size:.7rem; text-transform:uppercase; color:#888; font-weight:600; }
    .imc-pill { display:inline-block; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:600; color:#fff; }
</style>

<!-- ── INFO DEL PACIENTE ─────────────────────────────────────── -->
<div class="px-4 pt-3 pb-2 border-bottom" style="background:#f8f9fa;">
    <div class="row g-3 align-items-start">
        <div class="col-md-7">
            <div class="d-flex gap-3 align-items-center mb-2">
                <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);
                            color:#fff;font-weight:700;font-size:1.2rem;display:flex;align-items:center;justify-content:center;">
                    <?php echo strtoupper(substr($pac['NOMBRES'],0,1) . substr($pac['APELLIDOS'],0,1)); ?>
                </div>
                <div>
                    <div class="fw-bold" style="font-size:1.05rem;">
                        <?php echo htmlspecialchars($pac['NOMBRES'] . ' ' . $pac['APELLIDOS']); ?>
                    </div>
                    <small class="text-muted">C.I. <?php echo htmlspecialchars($pac['CEDULA'] ?? '—'); ?></small>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3" style="font-size:.85rem;">
                <?php if ($edad !== null): ?>
                    <span><i class="bi bi-person-fill text-muted me-1"></i><?php echo $edad; ?> años</span>
                <?php endif; ?>
                <?php if ($tallaActual): ?>
                    <?php $tallaM = $tallaActual > 3 ? $tallaActual / 100 : $tallaActual; ?>
                    <span><i class="bi bi-rulers text-muted me-1"></i><?php echo number_format($tallaM, 2); ?> m</span>
                <?php endif; ?>
                <?php
                    $sexo   = $pac['SEX']    ?? '';
                    $genero = $pac['GENDER'] ?? '';
                    $invalid = ['', 'Default Select', 'default select'];
                    $mostrar = !in_array($genero, $invalid) ? $genero : (!in_array($sexo, $invalid) ? $sexo : null);
                    if ($mostrar):
                ?>
                    <span><i class="bi bi-gender-ambiguous text-muted me-1"></i><?php echo htmlspecialchars($mostrar); ?></span>
                <?php endif; ?>
                <span><i class="bi bi-telephone text-muted me-1"></i><?php echo htmlspecialchars($pac['TELEFONO'] ?? '—'); ?></span>
                <span><i class="bi bi-envelope text-muted me-1"></i><?php echo htmlspecialchars($pac['EMAIL'] ?? '—'); ?></span>
                <?php if (!empty($pac['FECHA_REGISTRO'])): ?>
                    <span><i class="bi bi-calendar-plus text-muted me-1"></i>Registrado: <?php echo date('d/m/Y', strtotime($pac['FECHA_REGISTRO'])); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="col-md-5">
            <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                <div class="text-center px-3 py-1 rounded" style="background:#e8f0fe;">
                    <div style="font-size:1.2rem;font-weight:700;color:#3d5af1;"><?php echo $totalCitas; ?></div>
                    <div class="info-label">Total citas</div>
                </div>
                <div class="text-center px-3 py-1 rounded" style="background:#ede7f6;">
                    <div style="font-size:1.2rem;font-weight:700;color:#6f42c1;"><?php echo $atendidas; ?></div>
                    <div class="info-label">Atendidas</div>
                </div>
                <div class="text-center px-3 py-1 rounded" style="background:#fff3cd;">
                    <div style="font-size:1.2rem;font-weight:700;color:#e67e22;"><?php echo $pendientes; ?></div>
                    <div class="info-label">Pendientes</div>
                </div>
                <div class="text-center px-3 py-1 rounded" style="background:#fdecea;">
                    <div style="font-size:1.2rem;font-weight:700;color:#c0392b;"><?php echo $canceladas; ?></div>
                    <div class="info-label">Canceladas</div>
                </div>
                <?php if ($imcProm): ?>
                <div class="text-center px-3 py-1 rounded" style="background:#e8f5e9;">
                    <div style="font-size:1.2rem;font-weight:700;color:#2e7d32;"><?php echo $imcProm; ?></div>
                    <div class="info-label">IMC prom.</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── TABLA DE CITAS ────────────────────────────────────────── -->
<div class="px-0">
    <?php if (empty($citas)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
            Este paciente no tiene citas registradas.
        </div>
    <?php else: ?>
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Tipo</th>
                <th>Doctor</th>
                <th class="text-center">Estado</th>
                <th class="text-center">IMC</th>
                <th class="text-center">Informe</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($citas as $c):
            $est      = $c['IDHISTORIAL'] ? 'A' : $c['ESTADO_CITA'];
            $estLabel = $est === 'A' ? 'Atendida' : $est;
            $bc       = badgeClass($est);
        ?>
        <tr>
            <td><?php echo date('d/m/Y', strtotime($c['FECHA_CITA'])); ?></td>
            <td>
                <?php echo substr($c['HORA_INICIO'],0,5); ?>
                <?php if ($c['HORA_FIN']): ?>
                    <small class="text-muted">– <?php echo substr($c['HORA_FIN'],0,5); ?></small>
                <?php endif; ?>
            </td>
            <td><small><?php echo htmlspecialchars($c['TIPO_CONSULTA'] ?? '—'); ?></small></td>
            <td><small><?php echo htmlspecialchars(trim($c['DOCTOR']) ?: '—'); ?></small></td>
            <td class="text-center">
                <span class="badge <?php echo $bc; ?>"
                      <?php echo $est === 'A' ? 'style="background:#6f42c1"' : ''; ?>>
                    <?php echo $estLabel; ?>
                </span>
            </td>
            <td class="text-center">
                <?php if ($c['IMC']): ?>
                    <span class="imc-pill" style="background:<?php echo imcColor((float)$c['IMC']); ?>">
                        <?php echo number_format($c['IMC'],1); ?>
                    </span>
                    <div style="font-size:.68rem;color:#888;"><?php echo imcLabel((float)$c['IMC']); ?></div>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="text-center">
                <?php if ($c['IDHISTORIAL']): ?>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                            onclick="verInforme(<?php echo $c['IDHISTORIAL']; ?>)"
                            title="Ver informe">
                        <i class="bi bi-file-earmark-text"></i>
                    </button>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
