<?php
/**
 * Cambia el idioma activo (ES/EN) en sesión y vuelve a la página anterior.
 */
session_start();

$lang = $_GET['lang'] ?? 'es';
$_SESSION['lang'] = ($lang === 'en') ? 'en' : 'es';

$volver = $_SERVER['HTTP_REFERER'] ?? 'home.php';
header("Location: $volver");
exit;
