<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

session_start();

// Obtiene el directorio del archivo actual y conecta config.php
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

// -------------------- FUNCIONALIDAD POST (codigo_dane) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_municipio_info') {
    $codigo_dane = $_POST['codigo_dane'];

    if (empty($codigo_dane)) {
        echo json_encode(['error' => 'El código DANE del municipio no puede estar vacío']);
        exit;
    }

    $sql = "SELECT descripcion_dep, descripcion_mun, region_id FROM municipio WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$codigo_dane]);

    if ($stmt === false) {
        echo json_encode(['error' => 'Error en la consulta: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }

    $municipio = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$municipio) {
        echo json_encode(['error' => 'No se encontró información para el municipio seleccionado']);
        exit;
    }

    echo json_encode($municipio);
    exit;
}

// -------------------- FUNCIONALIDAD GET (radicado) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['radicado'])) {
    $radicado = $_GET['radicado'];

    if (empty($radicado)) {
        echo json_encode(['success' => false, 'message' => 'Radicado no proporcionado']);
        exit;
    }

    $sql = "SELECT 
        s.numero_identificacion_titular,
        a.tipo_documento,
        s.nombre,
        s.segundo_n,
        s.primer_p,
        s.segundo_p,
        s.entidad_bancaria,
        s.numero_identificacion,
        s.tipo_cuenta,
        s.numero_cuenta,
        s.url_drive,
        s.val_rembolso,
        s.rad_via,
        a.primer_nombre,
        a.segundo_nombre,
        a.primer_apellido,
        a.segundo_apellido,
        a.tipo_documento,
        a.codigo_dane_municipio_atencion,
        a.telefono,
        a.celular_principal,
        a.correo_principal,
        a.direccion_Residencia_cargue
    FROM solicitudes s
    JOIN afiliado a 
  ON REPLACE(REPLACE(REPLACE(
         LTRIM(RTRIM(s.numero_identificacion_titular)),
         ' ', ''), '.', ''), '-', '')
   =
     REPLACE(REPLACE(REPLACE(
         LTRIM(RTRIM(a.numero_documento)),
         ' ', ''), '.', ''), '-', '')

    WHERE s.rad_via = ?";

    $stmt = sqlsrv_query($conn, $sql, [$radicado]);

    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontraron datos.']);
    }
    exit;
}

// Si no es GET ni POST válidos
echo json_encode(['error' => 'Solicitud no válida.']);
exit;
