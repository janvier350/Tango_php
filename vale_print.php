<?php
/*
 * Componente reutilizable para imprimir el VALE del lado del cliente (navegador),
 * SIN hacer una nueva petición al servidor. Esto evita consumir un "proceso
 * entrante" del hosting (límite bajo en cuentas compartidas) que causaba cortes
 * intermitentes (ERR_CONNECTION_CLOSED) al abrir imprimir_vale.php.
 *
 * Uso:
 *   include 'vale_print.php';   // una vez por página (antes de </body>)
 *   // y en cada fila, un botón con los datos del movimiento:
 *   <button type="button" onclick="abrirValeDesdeBtn(this)"
 *       data-id="..." data-fecha="YYYY-MM-DD" data-rec="0" data-ent="0"
 *       data-benef="..." data-inter2="..." data-concepto="..." data-proyecto="..."
 *       data-inffin="..." data-doc="..." data-banco="..." data-cheque="...">PDF</button>
 *
 * El diseño replica imprimir_vale.php. La oficina y el usuario se toman de la sesión.
 */
?>
<script>
(function () {
    var VALE_OFICINA = <?php echo json_encode($_SESSION["oficina"] ?? ''); ?>;
    var VALE_USUARIO = <?php echo json_encode($_SESSION["user_name"] ?? ''); ?>;

    function esc(s) {
        s = (s == null) ? '' : String(s);
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
    }
    function money(n) {
        n = parseFloat(n) || 0;
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fechaDMY(iso) {
        if (!iso) return '';
        var p = String(iso).split('-');
        if (p.length === 3) return p[2] + '/' + p[1] + '/' + p[0];
        return iso;
    }
    function ahora() {
        var d = new Date();
        var z = function (x) { return (x < 10 ? '0' : '') + x; };
        return z(d.getDate()) + '/' + z(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' +
               z(d.getHours()) + ':' + z(d.getMinutes()) + ':' + z(d.getSeconds());
    }

    window.abrirValeDesdeBtn = function (btn) {
        var d = btn.dataset;
        var rec = parseFloat(d.rec) || 0;
        var ent = parseFloat(d.ent) || 0;
        var esIngreso = rec > 0;
        var tipo = esIngreso ? 'INGRESO DE CAJA' : 'EGRESO DE CAJA';
        var color = esIngreso ? '#0097b2' : '#d32f2f';
        var bg    = esIngreso ? '#e0f7fa' : '#ffebee';
        var monto = Math.max(rec, ent);

        var html =
        '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">' +
        '<title>Vale #' + esc(d.id) + '</title><style>' +
        'body{font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;font-size:13px;color:#333;}' +
        '.vale-container{border:2px solid #000;border-left:15px solid ' + color + ';padding:25px;width:600px;margin:30px auto;background:#fff;box-shadow:5px 5px 15px rgba(0,0,0,.1);}' +
        '.header{text-align:center;border-bottom:2px solid ' + color + ';margin-bottom:20px;padding-bottom:10px;}' +
        '.header h3{margin:0;color:#000;letter-spacing:1px;}' +
        '.tipo-movimiento{color:' + color + ';font-size:18px;font-weight:bold;margin-top:5px;display:block;}' +
        '.monto-box{background:' + bg + ';border:2px dashed ' + color + ';padding:10px 20px;border-radius:8px;display:inline-block;}' +
        '.monto-label{font-size:12px;display:block;color:#555;font-weight:bold;}' +
        '.monto-valor{font-size:24px;font-weight:900;color:' + color + ';}' +
        '.info-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}' +
        '.detalle p{margin:8px 0;border-bottom:1px solid #eee;padding-bottom:3px;}' +
        '.detalle strong{color:#555;width:120px;display:inline-block;}' +
        '.firmas{margin-top:80px;display:flex;justify-content:space-between;}' +
        '.firma-box{border-top:1px solid #000;width:240px;text-align:center;padding-top:10px;}' +
        '.nombre-firma{display:block;font-size:11px;text-transform:uppercase;margin-top:5px;color:#666;}' +
        '.footer-print{margin-top:40px;font-size:10px;color:#999;border-top:1px solid #eee;padding-top:10px;text-align:center;font-style:italic;}' +
        '@media print{body{background:none;}.vale-container{box-shadow:none;margin:0 auto;}}' +
        '</style></head><body onload="window.print();">' +
        '<div class="vale-container">' +
          '<div class="header"><h3>OFICINA: ' + esc(VALE_OFICINA) + '</h3>' +
            '<span class="tipo-movimiento">' + tipo + ' #' + esc(d.id) + '</span></div>' +
          '<div class="info-row"><div><strong>Fecha:</strong> ' + esc(fechaDMY(d.fecha)) + '</div>' +
            '<div class="monto-box"><span class="monto-label">TOTAL RECIBIDO/ENTREGADO</span>' +
            '<span class="monto-valor">$' + money(monto) + '</span></div></div>' +
          '<div class="detalle">' +
            '<p><strong>Beneficiario:</strong> ' + esc(d.benef) + '</p>' +
            '<p><strong>Intermediario:</strong> ' + esc(d.inter2 || 'N/A') + '</p>' +
            '<p><strong>Concepto:</strong> ' + esc(d.concepto) + '</p>' +
            '<p><strong>Proyecto:</strong> ' + esc(d.proyecto || 'S/N') + '</p>' +
            '<p><strong>Inf. Finan:</strong> ' + esc(d.inffin) + '</p>' +
            '<p><strong>Referencia:</strong> ' + esc(d.doc || 'N/A') + '</p>' +
            '<p><strong>Banco/Medio:</strong> ' + esc(d.banco) + ' - ' + esc(d.cheque) + '</p>' +
          '</div>' +
          '<div class="firmas">' +
            '<div class="firma-box"><strong>Entrega Conforme</strong>' +
              '<span class="nombre-firma">' + esc(d.inter2 || 'N/A') + '</span></div>' +
            '<div class="firma-box"><strong>Recibe Conforme</strong>' +
              '<span class="nombre-firma">' + esc(d.benef) + '</span></div>' +
          '</div>' +
          '<div class="footer-print">Caja Chica | Generado por: ' + esc(VALE_USUARIO) +
            ' | ID Transaccion: ' + esc(d.id) + ' | Fecha Impresion: ' + ahora() + '</div>' +
        '</div></body></html>';

        var w = window.open('', '_blank');
        if (!w) {
            alert('Habilita las ventanas emergentes para imprimir el vale.');
            return;
        }
        w.document.open();
        w.document.write(html);
        w.document.close();
    };
})();
</script>
