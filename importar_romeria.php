<?php
/*
 * Importador de la Caja ROMERÍA desde el Excel (una sola vez).
 *
 * SEGURIDAD: solo Administrador (rol 1).
 * MODO PRUEBA por defecto: muestra lo que haría SIN insertar nada.
 *   - Para insertar de verdad:  importar_romeria.php?confirmar=SI
 *   - Para además marcarlos aprobados: &aprobar=SI
 *   - Si la caja ya tuviera movimientos, exige &forzar=SI (evita doble carga).
 *
 * Al terminar, BORRAR este archivo y romeria_data.php del servidor.
 */
date_default_timezone_set('America/Guayaquil');
require_once 'config.php';
require_once 'auth.php';
verificar_auth();
$conn->set_charset("utf8");

if (intval($_SESSION['user_rol'] ?? 0) !== 1) { die('Solo el Administrador puede ejecutar este importador.'); }

$confirmar = (($_GET['confirmar'] ?? '') === 'SI');
$aprobar   = (($_GET['aprobar']   ?? '') === 'SI');
$forzar    = (($_GET['forzar']    ?? '') === 'SI');

$OFICINA = 'ROMERIA';
$USUARIO = 'lisbeth.rosero';

function h($s){ return htmlspecialchars((string)$s); }

// --- Resolver oficina y usuario por nombre ---
$ro = $conn->query("SELECT ID_OFICINA FROM CTR_OFICINA WHERE UPPER(OFICINA)=UPPER('" . $conn->real_escape_string($OFICINA) . "') LIMIT 1");
$id_oficina = ($ro && $x=$ro->fetch_assoc()) ? (int)$x['ID_OFICINA'] : 0;
$ru = $conn->query("SELECT id FROM usuarios WHERE usuario='" . $conn->real_escape_string($USUARIO) . "' LIMIT 1");
$id_usuario = ($ru && $x=$ru->fetch_assoc()) ? (int)$x['id'] : 0;

$movs = @include 'romeria_data.php';
$ok_datos = is_array($movs);

// --- Caches de resolución ---
$cacheProy = []; $cacheCat = []; $cacheRep = [];
$creadosProy = []; $creadosCat = []; $creadosRep = [];

function res_categoria($conn, $cat, &$cacheCat, &$creadosCat, $crear) {
    $cat = trim($cat); if ($cat==='') $cat = 'GTO.OFIC';
    $k = mb_strtoupper($cat);
    if (isset($cacheCat[$k])) return $cacheCat[$k];
    $e = $conn->real_escape_string($cat);
    $r = $conn->query("SELECT id FROM cat_reposiciones WHERE UPPER(nombre)=UPPER('$e') LIMIT 1");
    if ($r && $row=$r->fetch_assoc()) { return $cacheCat[$k]=(int)$row['id']; }
    $creadosCat[$cat] = true;
    if (!$crear) return 0;
    $conn->query("INSERT INTO cat_reposiciones (nombre) VALUES ('$e')");
    return $cacheCat[$k] = (int)$conn->insert_id;
}
function res_inffin($conn, $cat, $det, &$cacheCat, &$cacheRep, &$creadosCat, &$creadosRep, $crear) {
    $cat = trim($cat); $det = trim($det);
    if ($cat==='' && $det==='') { $cat='GTO.OFIC'; $det='GTOS. ADM.'; }
    if ($det==='') $det = $cat;
    $k = mb_strtoupper($cat.'||'.$det);
    if (isset($cacheRep[$k])) return $cacheRep[$k];
    $ce=$conn->real_escape_string($cat); $de=$conn->real_escape_string($det);
    $r=$conn->query("SELECT b.ID_REPOSICION FROM CAT_REPOSICION b
                     JOIN cat_reposiciones a ON b.ID_CAT_REPOCICIONES=a.id
                     WHERE UPPER(a.nombre)=UPPER('$ce') AND UPPER(b.REPOSICION)=UPPER('$de') LIMIT 1");
    if ($r && $row=$r->fetch_assoc()) { return $cacheRep[$k]=(int)$row['ID_REPOSICION']; }
    $creadosRep[$cat.' / '.$det] = true;
    if (!$crear) return 0;
    $idcat = res_categoria($conn,$cat,$cacheCat,$creadosCat,true);
    $conn->query("INSERT INTO CAT_REPOSICION (REPOSICION, ID_CAT_REPOCICIONES) VALUES ('$de', $idcat)");
    return $cacheRep[$k] = (int)$conn->insert_id;
}
function res_proyecto($conn, $nombre, &$cacheProy, &$creadosProy, $crear) {
    $nombre=trim($nombre); if ($nombre==='') return 0;
    $k=mb_strtoupper($nombre);
    if (isset($cacheProy[$k])) return $cacheProy[$k];
    $e=$conn->real_escape_string($nombre);
    $r=$conn->query("SELECT ID_PROYECTO FROM PROYECTOS WHERE UPPER(PROYECTO)=UPPER('$e') LIMIT 1");
    if ($r && $row=$r->fetch_assoc()) return $cacheProy[$k]=(int)$row['ID_PROYECTO'];
    $creadosProy[$nombre]=true;
    if (!$crear) return 0;
    $conn->query("INSERT INTO PROYECTOS (PROYECTO) VALUES ('$e')");
    return $cacheProy[$k]=(int)$conn->insert_id;
}

// --- ¿La caja ya tiene movimientos? ---
$ya = 0;
if ($id_oficina > 0) {
    $rc = $conn->query("SELECT COUNT(*) AS n FROM movimientos WHERE ID_OFICINA = $id_oficina");
    $ya = $rc ? (int)$rc->fetch_assoc()['n'] : 0;
}

$errores = [];
if (!$ok_datos) $errores[] = 'No se pudo leer romeria_data.php.';
if ($id_oficina<=0) $errores[] = "No se encontró la oficina '$OFICINA'.";
if ($id_usuario<=0) $errores[] = "No se encontró el usuario '$USUARIO'.";

$puede_insertar = $confirmar && empty($errores) && ($ya===0 || $forzar);

// --- Procesar ---
$insertados = 0; $sum_r=0.0; $sum_e=0.0; $defaultados=0;
if ($puede_insertar) { $conn->begin_transaction(); }

$stmt = null;
if ($puede_insertar) {
    $cols = "(fecha, empresa, concepto, intermediario, importe_recibido, importe_entregado,
              vale_ref, doc_soporte, inf_fin, periodo, banco, cheque_num, ID_USUARIO,
              FECHA_REGISTRO_I_E, ID_PROYECTO, intermediario2, ID_OFICINA, ESTADO";
    $ph   = "?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?";
    if ($aprobar) { $cols .= ", ID_USUARIO_REVISA, FECHA_REVISION"; $ph .= ",?,?"; }
    $cols .= ")";
    $stmt = $conn->prepare("INSERT INTO movimientos $cols VALUES ($ph)");
}

if ($ok_datos) {
    foreach ($movs as $m) {
        $cat=$m['inf_cat']??''; $det=$m['inf_det']??'';
        if (trim($cat)==='' && trim($det)==='') $defaultados++;
        $inf = res_inffin($conn,$cat,$det,$cacheCat,$cacheRep,$creadosCat,$creadosRep,$puede_insertar);
        $idp = res_proyecto($conn,$m['proyecto']??'',$cacheProy,$creadosProy,$puede_insertar);
        $rec=(float)$m['rec']; $ent=(float)$m['ent'];
        $sum_r+=$rec; $sum_e+=$ent;

        if ($puede_insertar) {
            $fecha=$m['fecha']; $periodo=date('Y-n', strtotime($fecha)); $reg=$fecha.' 00:00:00';
            $empresa=$m['empresa']; $concepto=$m['concepto']; $benef=$m['beneficiario'];
            $vale=$m['vale']; $doc=$m['doc']; $banco=''; $cheque=''; $inter2=$m['intermediario2'];
            $estado='A';
            if ($aprobar) {
                $frev=$fecha.' 00:00:00';
                $id_revisor = intval($_SESSION['user_id']); // el admin que ejecuta el importador
                $stmt->bind_param("ssssddssssssisisisis",
                    $fecha,$empresa,$concepto,$benef,$rec,$ent,$vale,$doc,$inf,$periodo,$banco,$cheque,
                    $id_usuario,$reg,$idp,$inter2,$id_oficina,$estado,$id_revisor,$frev);
            } else {
                $stmt->bind_param("ssssddssssssisisis",
                    $fecha,$empresa,$concepto,$benef,$rec,$ent,$vale,$doc,$inf,$periodo,$banco,$cheque,
                    $id_usuario,$reg,$idp,$inter2,$id_oficina,$estado);
            }
            if ($stmt->execute()) $insertados++;
            else { $errores[]='Error al insertar ('.$fecha.'): '.$stmt->error; break; }
        }
    }
}

if ($puede_insertar) {
    if (empty($errores)) { $conn->commit(); }
    else { $conn->rollback(); $insertados=0; }
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Importar ROMERÍA</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light"><div class="container my-4" style="max-width:900px;">
<h3>Importador Caja ROMERÍA</h3>

<?php if (!empty($errores)): ?>
  <div class="alert alert-danger"><strong>Errores:</strong><ul class="mb-0">
  <?php foreach ($errores as $e) echo '<li>'.h($e).'</li>'; ?></ul></div>
<?php endif; ?>

<table class="table table-sm w-auto">
  <tr><td>Oficina</td><td><strong><?php echo h($OFICINA); ?></strong> (id <?php echo $id_oficina; ?>)</td></tr>
  <tr><td>Usuario</td><td><strong><?php echo h($USUARIO); ?></strong> (id <?php echo $id_usuario; ?>)</td></tr>
  <tr><td>Movimientos en el Excel</td><td><strong><?php echo $ok_datos?count($movs):0; ?></strong></td></tr>
  <tr><td>Total recibido</td><td>$<?php echo number_format($sum_r,2); ?></td></tr>
  <tr><td>Total entregado</td><td>$<?php echo number_format($sum_e,2); ?></td></tr>
  <tr><td>Saldo resultante</td><td><strong>$<?php echo number_format($sum_r-$sum_e,2); ?></strong></td></tr>
  <tr><td>Sin clasificación (→ GTO.OFIC / GTOS. ADM.)</td><td><?php echo $defaultados; ?></td></tr>
  <tr><td>Movimientos ya existentes en la caja</td><td><?php echo $ya; ?></td></tr>
</table>

<?php
function lista($titulo,$arr){ if(empty($arr))return; echo '<p class="mb-1"><strong>'.h($titulo).' ('.count($arr).'):</strong></p><ul class="small">';
  foreach(array_keys($arr) as $k) echo '<li>'.h($k).'</li>'; echo '</ul>'; }
lista('Proyectos nuevos que se crearían/crearon', $creadosProy);
lista('Inf. Financiera nueva que se crearía/creó', $creadosRep);
?>

<?php if ($puede_insertar): ?>
  <div class="alert alert-success"><strong>✅ Importación realizada.</strong>
    Se insertaron <strong><?php echo $insertados; ?></strong> movimientos<?php echo $aprobar?' (aprobados)':' (pendientes de aprobar)'; ?>.
    <br>Ahora <strong>borra</strong> <code>importar_romeria.php</code> y <code>romeria_data.php</code> del servidor.</div>
<?php elseif ($ya>0 && $confirmar && !$forzar): ?>
  <div class="alert alert-warning">La caja ya tiene <?php echo $ya; ?> movimientos. Para importar de todas formas agrega <code>&forzar=SI</code>.</div>
<?php elseif (empty($errores)): ?>
  <div class="alert alert-info"><strong>MODO PRUEBA.</strong> No se insertó nada. Revisa los números arriba.
    <br>Para importar de verdad: <a href="?confirmar=SI">?confirmar=SI</a>
    &nbsp;|&nbsp; e importar <em>ya aprobados</em>: <a href="?confirmar=SI&aprobar=SI">?confirmar=SI&amp;aprobar=SI</a></div>
<?php endif; ?>

</div></body></html>
