<?php
session_start();

if (!isset($_SESSION['tipo_usuario_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// URL privada de Power BI
$powerbi_url = "https://app.powerbi.com/view?r=eyJrIjoiNDM3Yzc2MDItZTQ3YS00OTUyLTljYWUtOGRiNDRmNjc1YzZmIiwidCI6ImUzODVjNjAyLTMzYWMtNDhlMC05OGI3LTAxYzQ3NDMyODFiZCIsImMiOjR9";

// Codifica la URL para ocultarla un poco más
$encoded_url = base64_encode(rawurlencode($powerbi_url));

header('Content-Type: application/json');
echo json_encode(['url' => $encoded_url]);
?>