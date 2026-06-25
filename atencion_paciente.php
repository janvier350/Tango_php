<?php
ob_start();
session_start();

require_once("class/funciones.php");
require_once("class/conexionBD.php");
$conexion = conectarse();

if(!isset($_SESSION["rol"])){
    header("Location: break.php");
    exit();
}
if (isset($_SESSION['expire']) && time() > $_SESSION['expire']) {
    session_destroy();
    header("Location: expirada.php");
    exit();
}

if(!isset($_GET['idCita']) || empty($_GET['idCita'])){
    die("Error: No se recibió el ID de la cita.");
}

$idCita = $conexion->real_escape_string($_GET['idCita']);

$sql = "SELECT
            P.IDPACIENTE, P.NOMBRES, P.APELLIDOS, P.FECHANACIMIENTO, P.SEX,
            P.EMAIL, P.TELEFONO, P.CEDULA, P.ADDRESS,
            A.FECHA_CITA, A.HORA_INICIO, A.IDDOCTOR,
            D.NOMBRES  AS DOC_NOMBRES,
            D.APELLIDOS AS DOC_APELLIDOS,
            D.ESPECIALIDAD,
            AG.DESCRIPCION AS AGENCIA_NOMBRE,
            AG.DIRECCION  AS AGENCIA_DIRECCION,
            AG.TELEFONO   AS AGENCIA_TEL,
            TC.NOMBRES    AS TIPO_CONSULTA
        FROM AG_CITA A
        INNER JOIN AG_PACIENTE     P  ON A.IDPACIENTE      = P.IDPACIENTE
        LEFT  JOIN ADM_DOCTOR      D  ON A.IDDOCTOR         = D.IDDOCTOR
        LEFT  JOIN ADM_AGENCIA     AG ON AG.IDAGENCIA        = 1
        LEFT  JOIN AG_TIPOCONSULTA TC ON A.IDTIPOCONSULTA   = TC.IDTIPOCONSULTA
        WHERE A.IDCITA = '$idCita'";

$res = $conexion->query($sql);
if (!$res || $res->num_rows == 0) {
    die("Error: No se encontró la cita especificada.");
}
$d = $res->fetch_assoc();

$sessionNombres   = $_SESSION['nombres']   ?? '';
$sessionApellidos = $_SESSION['apellidos'] ?? '';
$docNombreCompleto = trim($d['DOC_NOMBRES'] . ' ' . $d['DOC_APELLIDOS']);
$doctorAtiende = ($sessionNombres || $sessionApellidos)
    ? trim($sessionNombres . ' ' . $sessionApellidos)
    : $docNombreCompleto;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atención - <?php echo htmlspecialchars($d['NOMBRES'].' '.$d['APELLIDOS']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link href="main.css" rel="stylesheet">
    <script src="js/jquery.min.js"></script>
    <style>
        .unit-toggle .btn { padding: 2px 8px; font-size: 0.78rem; }
        .medicion-group { display: flex; gap: 6px; align-items: center; }
        .medicion-group input { max-width: 100px; }

        /* ── Dictado por voz ── */
        @keyframes micPulse { 0%,100%{opacity:1} 50%{opacity:.25} }
        .mic-pulse { animation: micPulse 1s infinite; display:inline-block; }
        #btnMic { transition: all .2s; }
        #interimText {
            max-width: 520px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="app-container app-theme-white body-tabs-shadow fixed-sidebar fixed-header">

    <!-- HEADER -->
    <div class="app-header header-shadow">
        <div class="app-header__logo">
            <div class="logo-src"></div>
            <div class="header__pane ml-auto">
                <button type="button" class="hamburger close-sidebar-btn hamburger--elastic" data-class="closed-sidebar">
                    <span class="hamburger-box"><span class="hamburger-inner"></span></span>
                </button>
            </div>
        </div>
        <div class="app-header__mobile-menu">
            <button type="button" class="hamburger hamburger--elastic mobile-toggle-nav">
                <span class="hamburger-box"><span class="hamburger-inner"></span></span>
            </button>
        </div>
        <div class="app-header__menu">
            <button type="button" class="btn-icon btn-icon-only btn btn-primary btn-sm mobile-toggle-header-nav">
                <span class="btn-icon-wrapper"><i class="fa fa-ellipsis-v fa-w-6"></i></span>
            </button>
        </div>
        <div class="app-header__content">
            <div class="app-header-left"></div>
            <div class="app-header-right">
                <div class="header-btn-lg pr-0">
                    <div class="widget-content p-0">
                        <div class="widget-content-wrapper">
                            <div class="widget-content-left ml-3 header-user-info">
                                <div class="widget-heading"><?php echo htmlspecialchars($sessionNombres); ?></div>
                                <div class="widget-subheading"><?php echo htmlspecialchars($_SESSION['rol'] ?? ''); ?></div>
                            </div>
                            <div class="widget-content-left ms-3">
                                <div class="btn-group">
                                    <a data-toggle="dropdown" class="p-0 btn" href="#">
                                        <i class="fa fa-angle-down ml-2 opacity-8"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a href="salir.php" class="dropdown-item">Cerrar Sesión</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-main">
        <!-- SIDEBAR -->
        <div class="app-sidebar sidebar-shadow">
            <?php include("./menu/menu_adm.php"); ?>
        </div>

        <div class="app-main__outer">
            <div class="app-main__inner">

<div class="container-fluid mt-1 mb-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-person-lines-fill me-2"></i>
                Atención Nutricional: <?php echo htmlspecialchars($d['NOMBRES'].' '.$d['APELLIDOS']); ?>
            </h5>
            <span class="badge bg-light text-primary fs-6">Cita #<?php echo $idCita; ?></span>
        </div>

        <div class="card-body">

            <!-- ── MEDICIONES ──────────────────────────────────────────── -->
            <div class="row g-3 mb-4 bg-light p-3 border rounded align-items-end">

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Peso</label>
                    <div class="medicion-group">
                        <input type="number" id="peso" class="form-control" step="0.1"
                               placeholder="0.0" oninput="calcularIMC()">
                        <div class="btn-group unit-toggle" role="group">
                            <input type="radio" class="btn-check" name="unidadPeso" id="uKg" value="kg" checked onchange="calcularIMC()">
                            <label class="btn btn-outline-secondary" for="uKg">kg</label>
                            <input type="radio" class="btn-check" name="unidadPeso" id="uLbs" value="lbs" onchange="calcularIMC()">
                            <label class="btn btn-outline-secondary" for="uLbs">lbs</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Talla</label>
                    <div class="medicion-group">
                        <input type="number" id="talla" class="form-control" step="0.1"
                               placeholder="0.0" oninput="calcularIMC()">
                        <div class="btn-group unit-toggle" role="group">
                            <input type="radio" class="btn-check" name="unidadTalla" id="uCm" value="cm" checked onchange="calcularIMC()">
                            <label class="btn btn-outline-secondary" for="uCm">cm</label>
                            <input type="radio" class="btn-check" name="unidadTalla" id="uM" value="m" onchange="calcularIMC()">
                            <label class="btn btn-outline-secondary" for="uM">m</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">IMC</label>
                    <input type="text" id="imc" class="form-control bg-white fw-bold" readonly>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <div id="estado_imc" class="badge p-2 d-block fs-6">---</div>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Plantilla de Informe</label>
                    <select id="selPlantilla" class="form-select" onchange="cargarPlantilla(this.value)">
                        <option value="">-- Seleccione Tipo de Nota --</option>
                        <?php
                        $plantillas = $conexion->query(
                            "SELECT id, nombre_plantilla FROM cat_plantillas_nutricion ORDER BY categoria, nombre_plantilla"
                        );
                        while($p = $plantillas->fetch_assoc()):
                        ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre_plantilla']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- ── BARRA DE DICTADO ────────────────────────────────────── -->
            <div class="d-flex align-items-center gap-3 mb-2 p-2 rounded"
                 style="background:#f8f9fa;border:1px solid #e9ecef;">
                <button type="button" id="btnMic"
                        class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1"
                        onclick="toggleDictado()">
                    <i class="bi bi-mic-fill"></i> Iniciar dictado
                </button>

                <!-- Toggle idioma -->
                <div class="d-flex align-items-center gap-1" title="Idioma del micrófono">
                    <span id="langES" class="badge"
                          style="cursor:pointer;background:#0264d6;font-size:.7rem;"
                          onclick="setLang('es')">ES</span>
                    <span id="langEN" class="badge bg-secondary"
                          style="cursor:pointer;font-size:.7rem;"
                          onclick="setLang('en')">EN</span>
                </div>

                <div id="micStatus" class="d-none d-flex align-items-center gap-2">
                    <span class="badge bg-danger d-flex align-items-center gap-1">
                        <span class="mic-pulse">●</span> Escuchando
                    </span>
                    <small id="interimText" class="text-muted fst-italic"></small>
                </div>

                <small class="text-muted ms-auto">
                    <i class="bi bi-info-circle me-1"></i>
                    Chrome / Edge
                </small>
            </div>

            <!-- ── EDITOR ──────────────────────────────────────────────── -->
            <div class="mb-3">
                <textarea id="editorInforme" name="informe"></textarea>
            </div>

            <!-- ── BOTONES ─────────────────────────────────────────────── -->
            <div class="d-flex justify-content-between align-items-center">
                <a href="SCH_Calendar.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="imprimirInforme()">
                        <i class="bi bi-printer"></i> Vista Previa e Impresión
                    </button>
                    <button class="btn btn-success px-4" onclick="guardarAtencion()">
                        <i class="bi bi-file-earmark-check"></i> Guardar y Finalizar Atención
                    </button>
                </div>
            </div>

        </div>
    </div>
</div><!-- /container-fluid -->

            </div><!-- /app-main__inner -->
        </div><!-- /app-main__outer -->
    </div><!-- /app-main -->
</div><!-- /app-container -->

<script>
const DATOS_CITA = {
    idCita:          "<?php echo $idCita; ?>",
    pacienteNombre:  "<?php echo addslashes(htmlspecialchars($d['NOMBRES'].' '.$d['APELLIDOS'])); ?>",
    pacienteDOB:     "<?php echo $d['FECHANACIMIENTO']; ?>",
    pacienteEmail:   "<?php echo addslashes($d['EMAIL']); ?>",
    pacienteTel:     "<?php echo addslashes($d['TELEFONO']); ?>",
    pacienteCedula:  "<?php echo addslashes($d['CEDULA']); ?>",
    docNombre:       "<?php echo addslashes($docNombreCompleto); ?>",
    docApellido:     "<?php echo addslashes($d['DOC_APELLIDOS']); ?>",
    docEspecialidad: "<?php echo addslashes($d['ESPECIALIDAD']); ?>",
    atiendNombre:    "<?php echo addslashes($doctorAtiende); ?>",
    atiendApellido:  "<?php echo addslashes($sessionApellidos); ?>",
    agenciaNombre:   "<?php echo addslashes($d['AGENCIA_NOMBRE']); ?>",
    agenciaDirec:    "<?php echo addslashes($d['AGENCIA_DIRECCION']); ?>",
    agenciaTel:      "<?php echo addslashes($d['AGENCIA_TEL']); ?>",
    tipoConsulta:    "<?php echo addslashes($d['TIPO_CONSULTA']); ?>",
    fechaCita:       "<?php echo $d['FECHA_CITA']; ?>",
    fechaHoy:        "<?php echo date('d/m/Y'); ?>"
};
</script>

<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

// ── INICIALIZAR EDITOR ───────────────────────────────────────────────
$(document).ready(function(){
    $('#editorInforme').summernote({
        placeholder: 'Cargue una plantilla o escriba / dicte la nota aquí...',
        tabsize: 2,
        height: 520,
        lang: 'es-ES',
        toolbar: [
            ['style',  ['style']],
            ['font',   ['bold','underline','clear']],
            ['color',  ['color']],
            ['para',   ['ul','ol','paragraph']],
            ['table',  ['table']],
            ['insert', ['link','picture']],
            ['view',   ['fullscreen','codeview','help']]
        ]
    });
});

// ── CALCULAR IMC ─────────────────────────────────────────────────────
function calcularIMC(){
    const pesoInput  = parseFloat($('#peso').val());
    const tallaInput = parseFloat($('#talla').val());
    const uPeso  = $('input[name="unidadPeso"]:checked').val();
    const uTalla = $('input[name="unidadTalla"]:checked').val();
    if(!pesoInput || !tallaInput) return;
    const pesoKg = uPeso  === 'lbs' ? pesoInput  * 0.453592 : pesoInput;
    const tallaM = uTalla === 'cm'  ? tallaInput / 100       : tallaInput;
    const imc = (pesoKg / (tallaM * tallaM)).toFixed(2);
    $('#imc').val(imc);
    const est = $('#estado_imc');
    if      (imc < 18.5) est.text('Bajo Peso') .attr('class','badge p-2 d-block fs-6 bg-info');
    else if (imc < 25)   est.text('Normal')     .attr('class','badge p-2 d-block fs-6 bg-success');
    else if (imc < 30)   est.text('Sobrepeso')  .attr('class','badge p-2 d-block fs-6 bg-warning text-dark');
    else                 est.text('Obesidad')   .attr('class','badge p-2 d-block fs-6 bg-danger');
}

// ── CARGAR PLANTILLA ─────────────────────────────────────────────────
function cargarPlantilla(id){
    if(!id) return;
    const uPeso  = $('input[name="unidadPeso"]:checked').val()  || 'kg';
    const uTalla = $('input[name="unidadTalla"]:checked').val() || 'cm';
    const pesoVal  = $('#peso').val()  || '---';
    const tallaVal = $('#talla').val() || '---';
    const imcVal   = $('#imc').val()   || '---';
    const fechaNacJS = new Date(DATOS_CITA.pacienteDOB + 'T00:00:00');
    const dobFormateada = fechaNacJS.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});
    const firmaHtml = `<br><br>
        <div style="margin-top:40px;border-top:1px solid #ccc;padding-top:10px;font-family:Arial,sans-serif;">
            <strong>${DATOS_CITA.atiendNombre}</strong><br>
            <em>${DATOS_CITA.docEspecialidad}</em><br>
            ${DATOS_CITA.agenciaNombre}<br>${DATOS_CITA.agenciaTel}
        </div>`;
    $.ajax({
        url: 'get_plantilla_html.php', type: 'GET', data: { id: id },
        success: function(html){
            const vars = {
                '{{fecha_actual}}': DATOS_CITA.fechaHoy,
                '{{fecha_evaluacion}}': DATOS_CITA.fechaHoy,
                '{{fecha_cita}}': DATOS_CITA.fechaCita,
                '{{paciente_nombre}}': DATOS_CITA.pacienteNombre,
                '{{paciente_dob}}': dobFormateada,
                '{{paciente_email}}': DATOS_CITA.pacienteEmail,
                '{{paciente_telefono}}': DATOS_CITA.pacienteTel,
                '{{paciente_cedula}}': DATOS_CITA.pacienteCedula,
                '{{doctor_nombre}}': DATOS_CITA.docNombre,
                '{{nombre_doctor}}': DATOS_CITA.docNombre,
                '{{apellido_doctor}}': DATOS_CITA.docApellido,
                '{{titulo_doctor}}': 'Dr./Dra.',
                '{{especialidad}}': DATOS_CITA.docEspecialidad,
                '{{firma_nombre}}': DATOS_CITA.atiendNombre,
                '{{firma_credenciales}}': DATOS_CITA.docEspecialidad,
                '{{practica_nombre}}': DATOS_CITA.agenciaNombre,
                '{{direccion_1}}': DATOS_CITA.agenciaDirec,
                '{{direccion_2}}': '',
                '{{ciudad}}': DATOS_CITA.agenciaNombre,
                '{{estado}}': '',
                '{{codigo_postal}}': '',
                '{{telefono_clinica}}': DATOS_CITA.agenciaTel,
                '{{tipo_consulta}}': DATOS_CITA.tipoConsulta,
                '{{diagnostico_referencia}}': DATOS_CITA.tipoConsulta,
                '{{peso}}': `${pesoVal} ${uPeso}`,
                '{{talla}}': `${tallaVal} ${uTalla}`,
                '{{imc}}': imcVal,
                '{{antropometria}}': `Peso: ${pesoVal} ${uPeso}, Talla: ${tallaVal} ${uTalla}, IMC: ${imcVal}`,
                '{{bioquimica}}': '[Ingresar datos bioquímicos]',
                '{{hallazgos_fisicos}}': '[Ingresar hallazgos físicos]',
                '{{historial_cliente}}': '[Ingresar historial del paciente]',
                '{{plan_nutricional}}': '[Ingresar plan nutricional]',
                '{{objetivos}}': '[Ingresar objetivos]',
                '{{recomendaciones}}': '[Ingresar recomendaciones]',
                '{{diagnostico}}': '[Ingresar diagnóstico]',
                '{{tratamiento}}': '[Ingresar tratamiento]',
                '{{diagnostico_nutricional}}': '[Ingresar diagnóstico nutricional]',
                '{{intervencion}}': '[Ingresar intervención nutricional]',
                '{{monitoreo}}': '[Ingresar monitoreo y evaluación]',
                '{{historial_nutricional_seguimiento}}': '[Ingresar historial nutricional]',
                '{{datos_seguimiento}}': `Peso: ${pesoVal} ${uPeso}, Talla: ${tallaVal} ${uTalla}, IMC: ${imcVal}`,
                '{{diagnostico_pes_seguimiento}}': '[Ingresar diagnóstico PES]',
                '{{prescripcion_nutricional}}': '[Ingresar prescripción nutricional]',
                '{{plan_accion_seguimiento}}': '[Ingresar plan de acción]',
                '{{monitoreo_indicador}}': '[Indicador]',
                '{{monitoreo_meta}}': '[Meta]',
                '{{monitoreo_progreso}}': '[Progreso]',
                '{{req_energia}}': '---',
                '{{ingesta_energia}}': '---',
                '{{req_proteina}}': '---',
                '{{ingesta_proteina}}': '---',
                '{{req_fluidos}}': '---',
                '{{ingesta_fluidos}}': '---',
            };
            for(const [tag, val] of Object.entries(vars)){
                const regex = new RegExp(tag.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'g');
                html = html.replace(regex, val);
            }
            html += firmaHtml;
            $('#editorInforme').summernote('code', html);
        },
        error: function(){ alert('Error al cargar la plantilla.'); }
    });
}

// ── GUARDAR ATENCIÓN ─────────────────────────────────────────────────
function guardarAtencion(){
    const contenido = $('#editorInforme').summernote('code');
    if(contenido.replace(/<[^>]+>/g,'').trim().length < 10){
        alert('El informe parece estar vacío. Complételo antes de guardar.');
        return;
    }
    const uPeso  = $('input[name="unidadPeso"]:checked').val()  || 'kg';
    const uTalla = $('input[name="unidadTalla"]:checked').val() || 'cm';
    let pesoVal  = parseFloat($('#peso').val())  || 0;
    let tallaVal = parseFloat($('#talla').val()) || 0;
    const pesoKg  = uPeso  === 'lbs' ? pesoVal  * 0.453592 : pesoVal;
    const tallaCm = uTalla === 'm'   ? tallaVal * 100       : tallaVal;
    const imcVal  = parseFloat($('#imc').val()) || 0;
    if(!confirm('¿Desea finalizar la atención y guardar el historial?')) return;
    $.ajax({
        url: 'guardar_atencion.php', type: 'POST',
        data: { idCita: DATOS_CITA.idCita, informe: contenido,
                peso: pesoKg.toFixed(2), talla: tallaCm.toFixed(1), imc: imcVal },
        success: function(res){
            if(res.trim() === 'OK'){
                alert('Atención guardada con éxito.');
                window.location.href = 'SCH_Calendar.php';
            } else { alert('Error al guardar: ' + res); }
        },
        error: function(){ alert('Error de conexión al guardar. Intente de nuevo.'); }
    });
}

// ── IMPRIMIR ──────────────────────────────────────────────────────────
function imprimirInforme(){
    const contenido = $('#editorInforme').summernote('code');
    const ventana = window.open('','_blank','height=800,width=900');
    ventana.document.write(`<html><head>
        <title>Informe - ${DATOS_CITA.pacienteNombre}</title>
        <style>body{font-family:Arial,sans-serif;padding:40px;color:#333;}</style>
        </head><body>${contenido}</body></html>`);
    ventana.document.close();
    ventana.focus();
    setTimeout(() => ventana.print(), 500);
}

// ── DICTADO POR VOZ ───────────────────────────────────────────────────
let recognition   = null;
let dictadoActivo = false;
let savedRange    = null;   // guarda posición del cursor al iniciar dictado
let micLang       = 'es-EC';

function setLang(lang) {
    if (dictadoActivo) return; // no cambiar mientras escucha
    if (lang === 'es') {
        micLang = 'es-EC';
        document.getElementById('langES').style.background = '#0264d6';
        document.getElementById('langES').classList.remove('bg-secondary');
        document.getElementById('langEN').style.background = '';
        document.getElementById('langEN').classList.add('bg-secondary');
    } else {
        micLang = 'en-US';
        document.getElementById('langEN').style.background = '#198754';
        document.getElementById('langEN').classList.remove('bg-secondary');
        document.getElementById('langES').style.background = '';
        document.getElementById('langES').classList.add('bg-secondary');
    }
}

function toggleDictado() {
    if (!dictadoActivo) {
        iniciarDictado();
    } else {
        detenerDictado();
    }
}

function iniciarDictado() {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
        alert('Tu navegador no soporta dictado por voz.\nUsa Google Chrome o Microsoft Edge.');
        return;
    }

    // Guardar posición actual del cursor en el editor antes de empezar
    var sel = window.getSelection();
    savedRange = (sel && sel.rangeCount > 0) ? sel.getRangeAt(0).cloneRange() : null;

    recognition = new SR();
    recognition.lang         = micLang;
    recognition.continuous   = true;       // sigue escuchando sin timeout
    recognition.interimResults = true;     // muestra texto parcial en tiempo real

    recognition.onresult = function(event) {
        let interim = '';
        let finalText = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
            if (event.results[i].isFinal) {
                finalText += event.results[i][0].transcript + ' ';
            } else {
                interim += event.results[i][0].transcript;
            }
        }
        // Mostrar texto parcial en vivo
        document.getElementById('interimText').textContent = interim;

        // Insertar texto final en el editor Summernote
        if (finalText) {
            var $editable = $('.note-editable').first();
            $editable[0].focus();

            // Restaurar posición guardada; si no hay, ir al final
            var sel2 = window.getSelection();
            sel2.removeAllRanges();
            if (savedRange) {
                try { sel2.addRange(savedRange); } catch(e) { savedRange = null; }
            }
            if (!savedRange || sel2.rangeCount === 0) {
                var range = document.createRange();
                range.selectNodeContents($editable[0]);
                range.collapse(false);
                sel2.removeAllRanges();
                sel2.addRange(range);
            }

            $('#editorInforme').summernote('insertText', finalText);

            // Actualizar savedRange a la nueva posición (después del texto insertado)
            var sel3 = window.getSelection();
            savedRange = (sel3 && sel3.rangeCount > 0) ? sel3.getRangeAt(0).cloneRange() : null;

            document.getElementById('interimText').textContent = '';
        }
    };

    recognition.onerror = function(event) {
        if (event.error === 'no-speech') return; // silencio temporal, ignorar
        detenerDictado();
        if (event.error === 'not-allowed') {
            alert('Permiso de micrófono denegado.\nHabilita el acceso al micrófono en tu navegador.');
        } else {
            console.warn('Error de reconocimiento:', event.error);
        }
    };

    // Si el browser detiene el reconocimiento y sigue activo → reiniciar
    recognition.onend = function() {
        if (dictadoActivo) recognition.start();
    };

    recognition.start();
    dictadoActivo = true;

    // UI: estado activo
    document.getElementById('btnMic').innerHTML =
        '<i class="bi bi-mic-mute-fill"></i> Detener dictado';
    document.getElementById('btnMic').className =
        'btn btn-danger btn-sm d-flex align-items-center gap-1';
    document.getElementById('micStatus').classList.remove('d-none');
}

function detenerDictado() {
    dictadoActivo = false;
    if (recognition) { recognition.stop(); recognition = null; }

    // UI: estado inactivo
    document.getElementById('btnMic').innerHTML =
        '<i class="bi bi-mic-fill"></i> Iniciar dictado';
    document.getElementById('btnMic').className =
        'btn btn-outline-danger btn-sm d-flex align-items-center gap-1';
    document.getElementById('micStatus').classList.add('d-none');
    document.getElementById('interimText').textContent = '';
}
</script>
</body>
</html>
