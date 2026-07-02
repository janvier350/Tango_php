<?php
// 1. CABECERA PARA FORZAR UTF-8 (Soluciona tildes en el navegador)
header('Content-Type: text/html; charset=utf-8');
// 1. CONFIGURACI�0�7N DE ZONA HORARIA
date_default_timezone_set('America/Guayaquil'); 

require_once 'config.php';
require_once 'auth.php'; // Necesario para obtener el nombre del usuario de la sesi��n
verificar_auth();

$id = $_GET['id'];

// Traemos info del movimiento, proyectos y el nombre del usuario que registr��
$stmt = $conn->prepare("SELECT m.*, r.REPOSICION, p.PROYECTO, u.nombres as nombre_usuario_registro
                        FROM movimientos m 
                        LEFT JOIN CAT_REPOSICION r ON m.inf_fin = r.ID_REPOSICION 
                        LEFT JOIN PROYECTOS p ON m.ID_PROYECTO = p.ID_PROYECTO 
                        LEFT JOIN usuarios u ON m.ID_USUARIO = u.id
                        WHERE m.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$val = $stmt->get_result()->fetch_assoc();

// Determinamos si es INGRESO o EGRESO y definimos colores
$es_ingreso = ($val['importe_recibido'] > 0);
$tipo_texto = $es_ingreso ? "INGRESO DE CAJA" : "EGRESO DE CAJA";
$color_principal = $es_ingreso ? "#0097b2" : "#d32f2f"; // Celeste vs Rojo
$bg_ligero = $es_ingreso ? "#e0f7fa" : "#ffebee"; // Fondos tenues para el monto

$fecha_impresion = date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>Vale #<?php echo $id; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; color: #333; }
        
        .vale-container { 
            border: 2px solid #000; 
            border-left: 15px solid <?php echo $color_principal; ?>; /* Indicador visual lateral */
            padding: 25px; 
            width: 600px; 
            margin: 30px auto; 
            position: relative;
            background-color: #fff;
            box-shadow: 5px 5px 15px rgba(0,0,0,0.1);
        }

        .header { text-align: center; border-bottom: 2px solid <?php echo $color_principal; ?>; margin-bottom: 20px; padding-bottom: 10px; }
        .header h3 { margin: 0; color: #000; letter-spacing: 1px; }
        .tipo-movimiento { 
            color: <?php echo $color_principal; ?>; 
            font-size: 18px; 
            font-weight: bold; 
            margin-top: 5px; 
            display: block; 
        }

        .monto-box {
            background-color: <?php echo $bg_ligero; ?>;
            border: 2px dashed <?php echo $color_principal; ?>;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
        }
        .monto-label { font-size: 12px; display: block; color: #555; font-weight: bold; }
        .monto-valor { font-size: 24px; font-weight: 900; color: <?php echo $color_principal; ?>; }

        .info-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        
        .detalle p { margin: 8px 0; border-bottom: 1px solid #eee; padding-bottom: 3px; }
        .detalle strong { color: #555; width: 120px; display: inline-block; }

        .firmas { margin-top: 80px; display: flex; justify-content: space-between; }
        .firma-box { 
            border-top: 1px solid #000; 
            width: 240px; 
            text-align: center; 
            padding-top: 10px; 
        }
        .nombre-firma { display: block; font-size: 11px; text-transform: uppercase; margin-top: 5px; color: #666; }

        .footer-print { 
            margin-top: 40px; 
            font-size: 10px; 
            color: #999; 
            border-top: 1px solid #eee; 
            padding-top: 10px;
            text-align: center;
            font-style: italic;
        }

        @media print {
            body { background: none; }
            .vale-container { box-shadow: none; margin: 0 auto; }
        }
    </style>
</head>
<body onload="window.print();"> 
    <div class="vale-container">
        <div class="header">
            <!-- <h3><?php echo htmlspecialchars($val['empresa']); ?></h3> -->
            <h3>OFICINA:  <?php echo htmlspecialchars($_SESSION["oficina"]); ?></h3>
            <!-- <a class="dropdown-item" href="#">OFICINA: <?php echo $_SESSION["oficina"]; ?></a> -->
            
            <span class="tipo-movimiento"><?php echo $tipo_texto; ?> #<?php echo $val['id']; ?></span>
        </div>
        
        <div class="info-row">
            <div><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($val['fecha'])); ?></div>
            <div class="monto-box">
                <span class="monto-label">TOTAL RECIBIDO/ENTREGADO</span>
                <span class="monto-valor">$<?php echo number_format(max($val['importe_recibido'], $val['importe_entregado']), 2); ?></span>
            </div>
        </div>
        
        <div class="detalle">
            <p><strong>Beneficiario:</strong> <?php echo htmlspecialchars($val['intermediario']); ?></p>
            <p><strong>Intermediario:</strong> <?php echo htmlspecialchars($val['INTERMEDIARIO2'] ?: 'N/A'); ?></p>
            <p><strong>Concepto:</strong> <?php echo htmlspecialchars($val['concepto']); ?></p>
            <p><strong>Proyecto:</strong> <?php echo htmlspecialchars($val['PROYECTO'] ?? 'S/N'); ?></p>
            <p><strong>Inf. Finan:</strong> <?php echo htmlspecialchars($val['REPOSICION']); ?></p>
            <p><strong>Referencia:</strong> <?php echo htmlspecialchars($val['doc_soporte'] ?: 'N/A'); ?></p>
            <p><strong>Banco/Medio:</strong> <?php echo htmlspecialchars($val['banco']); ?> - <?php echo htmlspecialchars($val['cheque_num']); ?></p>
        </div>

        <div class="firmas">
            <div class="firma-box">
                <strong>Entrega Conforme</strong>
                <span class="nombre-firma"><?php echo htmlspecialchars($val['INTERMEDIARIO2'] ?: 'N/A'); ?></span>
            </div>

            <div class="firma-box">
                <strong>Recibe Conforme</strong>
                <span class="nombre-firma"><?php echo htmlspecialchars($val['intermediario']); ?></span>
            </div>
        </div>

        <div class="footer-print">
            Caja Chica  | Generado por: <?php echo $_SESSION["user_name"]; ?> | ID Transaccion: <?php echo $id; ?> | Fecha Impresion: <?php echo $fecha_impresion; ?>
        </div>
    </div>
</body>
</html>