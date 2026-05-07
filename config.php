<?php
// --- CONFIGURACIÓN DE CONEXIÓN ---
$host = "buadnet.com.ec";
$user = "buadnetc_flujo";
$pass = "]h(N{WS4ep[,}k8E";
$db   = "buadnetc_flujo_caja";

$conn = new mysqli($host, $user, $pass, $db);

// Verificar conexión
if ($conn->connect_error) { 
    die("Error crítico de conexión: " . $conn->connect_error); 
}

$conn->set_charset("utf8mb4"); // Para que los acentos se vean bien
$conn->set_charset("utf8");
?>