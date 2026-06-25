<?php
/**
 * Switch de idioma ES/EN. Requiere que la sesión ya esté iniciada y que
 * class/i18n.php (función idiomaActual()) haya sido incluido antes.
 */
$___lang = idiomaActual();
?>
<style>
    .lang-toggle { display: inline-flex; border: 1px solid #d8dce6; border-radius: 20px; overflow: hidden; }
    .lang-toggle a {
        padding: 4px 12px; font-size: .72rem; font-weight: 700; letter-spacing: .03em;
        color: #888; text-decoration: none; transition: background .15s, color .15s;
    }
    .lang-toggle a.active { background: #5a2d82; color: #fff; }
</style>
<div class="lang-toggle">
    <a href="set_lang.php?lang=es" class="<?php echo $___lang === 'es' ? 'active' : ''; ?>">ES</a>
    <a href="set_lang.php?lang=en" class="<?php echo $___lang === 'en' ? 'active' : ''; ?>">EN</a>
</div>
