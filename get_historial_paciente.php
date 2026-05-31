<?php
session_start();
require_once("class/funciones.php");
require_once("class/conexionBD.php");
$conexion = conectarse();

if (!isset($_SESSION["rol"])) { http_response_code(403); exit; }

$idPaciente = (int)($_GET['id'] ?? 0);
if (!$idPaciente) { echo '<p class="text-danger p-3">ID no válido.</p>'; exit; }

// Datos del paciente (solo columnas seguras)
$stmtP = $conexion->prepare(
    "SELECT NOMBRES, APELLIDOS, CEDULA, TELEFONO, EMAIL
     FROM AG_PACIENTE WHERE IDPACIENTE = ? LIMIT 1"
);
$stmtP->bind_param("i", $idPaciente);
$stmtP->execute();
$pac = $stmtP->get_result()->fetch_assoc();
$stmtP->close();

if (!$pac) { echo '<p class="text-danger p-3">Paciente no encontrado.</p>'; exit; }

// Todas las citas del paciente (sin H.ESTADO que puede no existir)
$stmtC = $conexion->prepare(
    "SELECT C.IDCITA, C.FECHA_CITA, C.HORA_INICIO, C.HORA_FIN,
            C.ESTADO_CITA,
            TC.NOMBRES AS TIPO_CONSULTA,
            CONCAT(D.NOMBRES,' ',D.APELLIDOS) AS DOCTOR,
            H.IDHISTORIAL, H.PESO, H.TALLA, H.IMC, H.FECHA_REGISTRO
     FROM AG_CITA C
     LEFT JOIN AG_TIPOCONSULTA TC ON TC.IDTIPOCONSULTA = C.IDTIPOCONSULTA
     LEFT JOIN ADM_DOCTOR D       ON D.IDDOCTOR        = C.IDDOCTOR
     LEFT JOIN AG_HISTORIAL H     ON H.IDCITA          = C.IDCITA
     WHERE C.IDPACIENTE = ? AND C.ESTADO = 'A'
     ORDER BY C.FECHA_CITA DESC, C.HORA_INICIO DESC"
);
$stmtC->bind_param("i", $idPaciente);
$stmtC->execute();
$citas = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtC->close();

// Contadores rápidos
$totalCitas    = count($citas);
$atendidas     = count(array_filter($citas, fn($c) => $c['IDHISTORIAL']));
$pendientes    = count(array_filter($citas, fn($c) => $c['ESTADO_CITA'] === 'Pendiente'));
$confirmadas   = count(array_filter($citas, fn($c) => $c['ESTADO_CITA'] === 'Confirmada'));
$canceladas    = count(array_filter($citas, fn($c) => in_array($c['ESTADO_CITA'], ['Cancelada','Cancelado'])));

// IMC promedio de atenciones
$imcs = array_filter(array_column($citas, 'IMC'));
$imcProm = count($imcs) ? number_format(array_sum($imcs) / count($imcs), 1) : null;

// Helpers
function badgeClass(string $est): string {
    return match($est) {
        'Confirmada'           => 'bg-success',
        'Pendiente'            => 'bg-warning text-dark',
        'Cancelada','Cancelado'=> 'bg-danger',
        'A'                    => 'badge-atendida',
        default                => 'bg-secondary',
    };
}
function imcLabel(float $imc): string {
    if ($imc < 18.5) return 'Bajo peso';
    if ($imc < 25)   return 'Normal';
    if ($imc < 30)   return 'Sobrepeso';
    return 'Obesidad';
}
function imcColor(float $imc): string {
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
                <span><i class="bi bi-telephone text-muted me-1"></i><?php echo htmlspecialchars($pac['TELEFONO'] ?? '—'); ?></span>
                <span><i class="bi bi-envelope text-muted me-1"></i><?php echo htmlspecialchars($pac['EMAIL'] ?? '—'); ?></span>
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
            $est = $c['IDHISTORIAL'] ? 'A' : $c['ESTADO_CITA'];
            $estLabel = $est === 'A' ? 'Atendida' : $est;
            $bc = badgeClass($est);
        ?>
        <tr>
            <td><?php echo date('d/m/Y', strtotime($c['FECHA_CITA'])); ?></td>
            <td><?php echo substr($c['HORA_INICIO'],0,5); ?>
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
                    <button class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2"
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

<!-- Sub-modal para ver informe -->
<div class="modal fade" id="modalInforme" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Informe de Atención</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cuerpoInforme">
                <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
            <div class="modal-footer py-2">
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function verInforme(idHistorial) {
    document.getElementById('cuerpoInforme').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    new bootstrap.Modal(document.getElementById('modalInforme')).show();
    $.get('get_informe_html.php', { id: idHistorial }, function(html) {
        document.getElementById('cuerpoInforme').innerHTML = html;
    });
}
</script>
