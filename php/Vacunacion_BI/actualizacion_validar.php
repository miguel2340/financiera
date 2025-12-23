<?php
session_start();
require_once '../../config.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

header('Content-Type: application/json');

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$MAX_SIZE = 10 * 1024 * 1024; // 10 MB
$ALLOWED_EXT = ['xlsx', 'xls'];

function respond($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['tipo_usuario_id']) || ($_SESSION['tipo_usuario_id'] != 7 && $_SESSION['tipo_usuario_id'] != 1)) {
    respond(['success' => false, 'message' => 'No autorizado'], 403);
}

if (!isset($_FILES['file'])) {
    respond(['success' => false, 'message' => 'No se envio archivo'], 400);
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED_EXT, true)) {
    respond(['success' => false, 'message' => 'Formato no valido. Usa .xlsx o .xls'], 400);
}
if ($file['size'] > $MAX_SIZE) {
    respond(['success' => false, 'message' => 'El archivo supera 10 MB'], 400);
}

try {
    $spreadsheet = IOFactory::load($file['tmp_name']);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'No se pudo leer el Excel: ' . $e->getMessage()], 400);
}

$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);
if (count($rows) < 2) {
    respond(['success' => false, 'message' => 'El archivo no tiene datos'], 400);
}

$expected = [
    'Region','Departamento','Municipio','HabitaEnMunicipioDeRiesgo','PrimerNombre','SegundoNombre',
    'PrimerApellido','SegundoApellido','TipoDocumento','NumeroDocumento','DocenteCotizante',
    'Sexo','FechaNacimiento','EdadEnMeses','EdadCumplida',
    'FechaAplicacionMinisterio','FechaAplicacionDepartamento',
    'NombreIpsVacunacion','CodigoHabilitacionIpsVacunacion'
];
$headerRow = $rows[1];
$headers = [
    trim($headerRow['A'] ?? ''),
    trim($headerRow['B'] ?? ''),
    trim($headerRow['C'] ?? ''),
    trim($headerRow['D'] ?? ''),
    trim($headerRow['E'] ?? ''),
    trim($headerRow['F'] ?? ''),
    trim($headerRow['G'] ?? ''),
    trim($headerRow['H'] ?? ''),
    trim($headerRow['I'] ?? ''),
    trim($headerRow['J'] ?? ''),
    trim($headerRow['K'] ?? ''),
    trim($headerRow['L'] ?? ''),
    trim($headerRow['M'] ?? ''),
    trim($headerRow['N'] ?? ''),
    trim($headerRow['O'] ?? ''),
    trim($headerRow['P'] ?? ''),
    trim($headerRow['Q'] ?? ''),
    trim($headerRow['R'] ?? ''),
    trim($headerRow['S'] ?? '')
];
if ($headers !== $expected) {
    respond(['success' => false, 'message' => 'Encabezados incorrectos. Verifica el formato exacto.'], 400);
}

function parseFechaDepto($value)
{
    if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
        return null;
    }
    try {
        if (is_numeric($value)) {
            $dt = ExcelDate::excelToDateTimeObject($value);
        } else {
            $dt = new DateTime($value);
        }
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

function parseEntero($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return (int)$value;
    }
    $clean = preg_replace('/[^0-9\\-]/', '', (string)$value);
    if ($clean === '' || !is_numeric($clean)) {
        return null;
    }
    return (int)$clean;
}

// Cambiar a la base de datos correcta
$useDb = sqlsrv_query($conn, "USE Vacunacion;");
if ($useDb === false) {
    respond(['success' => false, 'message' => 'No se pudo seleccionar la base Vacunacion', 'sqlsrv' => sqlsrv_errors()], 500);
}

$candidatos = [];
$rechazados = [];
$totalLeidas = count($rows) - 1;

// Obtener el último corte registrado en la tabla de resumen
$corteStmt = sqlsrv_query($conn, "SELECT MAX(corte) AS corte FROM Vacunacion.dbo.VacunacionResumen");
if ($corteStmt === false) {
    respond(['success' => false, 'message' => 'No se pudo obtener el corte objetivo', 'sqlsrv' => sqlsrv_errors()], 500);
}
$corteRow = sqlsrv_fetch_array($corteStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($corteStmt);
$CORTE_OBJETIVO = isset($corteRow['corte']) ? (int)$corteRow['corte'] : null;
if ($CORTE_OBJETIVO === null) {
    respond(['success' => false, 'message' => 'No se encontró corte en la tabla de resumen'], 400);
}

$selectSql = "SELECT FechaAplicacionMinisterio, FechaAplicacionDepartamento
FROM Vacunacion.dbo.VacunacionFiebreAmarilla
WHERE TipoDocumento = ? AND NumeroDocumento = ?";

for ($i = 2; $i <= count($rows); $i++) {
    $r = $rows[$i];
    $tipo = trim($r['I'] ?? '');
    $doc = trim($r['J'] ?? '');
    $edadMeses = parseEntero($r['N'] ?? null);
    $edadCumplida = parseEntero($r['O'] ?? null);
    $fechaRaw = $r['Q'] ?? '';
    $fechaDepto = parseFechaDepto($fechaRaw);
    $region = trim($r['A'] ?? '');
    $departamento = trim($r['B'] ?? '');
    $municipio = trim($r['C'] ?? '');
    $habitaRiesgo = trim($r['D'] ?? '');
    $docente = trim($r['K'] ?? '');
    $sexo = trim($r['L'] ?? '');

    if ($tipo === '' || $doc === '') {
        $rechazados[] = ['fila' => $i, 'doc' => $doc ?: '-', 'motivo' => 'Sin tipo o numero de documento'];
        continue;
    }
    if ($fechaDepto === null) {
        $rechazados[] = ['fila' => $i, 'doc' => $doc, 'motivo' => 'FechaAplicacionDepartamento vacia o invalida'];
        continue;
    }

    $stmt = sqlsrv_query($conn, $selectSql, [$tipo, $doc]);
    if ($stmt === false) {
        respond(['success' => false, 'message' => 'Error consultando base', 'sqlsrv' => sqlsrv_errors()], 500);
    }
    $dbRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    if (!$dbRow) {
        $rechazados[] = ['fila' => $i, 'doc' => $doc, 'motivo' => 'No coincide en la base para el corte'];
        continue;
    }

    if (!empty($dbRow['FechaAplicacionMinisterio']) || !empty($dbRow['FechaAplicacionDepartamento'])) {
        $rechazados[] = ['fila' => $i, 'doc' => $doc, 'motivo' => 'Registro ya tiene fecha cargada'];
        continue;
    }

    $candidatos[] = [
        'TipoDocumento' => $tipo,
        'NumeroDocumento' => $doc,
        'FechaAplicacionDepartamento' => $fechaDepto,
        'EdadEnMeses' => $edadMeses,
        'EdadCumplida' => $edadCumplida,
        'Region' => $region,
        'Departamento' => $departamento,
        'Municipio' => $municipio,
        'HabitaEnMunicipioDeRiesgo' => $habitaRiesgo,
        'DocenteCotizante' => $docente,
        'Sexo' => $sexo
    ];
}

// Guardamos en sesion para confirmar luego
$_SESSION['fa_actualizacion'] = [
    'corte' => $CORTE_OBJETIVO,
    'candidatos' => $candidatos
];

$previewLimit = 200;

respond([
    'success' => true,
    'message' => 'Validacion completada',
    'corte' => $CORTE_OBJETIVO,
    'total_leidas' => $totalLeidas,
    'candidatos_total' => count($candidatos),
    'rechazados_total' => count($rechazados),
    'candidatos_preview' => array_slice($candidatos, 0, $previewLimit),
    'rechazados_preview' => array_slice($rechazados, 0, $previewLimit)
]);
