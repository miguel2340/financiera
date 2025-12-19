<?php
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!isset($_SESSION['tipo_usuario_id']) || $_SESSION['tipo_usuario_id'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!isset($_SESSION['nuevo_corte'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay datos validados para confirmar']);
    exit;
}

$data = $_SESSION['nuevo_corte'];
$tempTable = $data['temp_table'] ?? null;

if (!$tempTable) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay aptos para actualizar']);
    exit;
}

$useDb = sqlsrv_query($conn, "USE Vacunacion;");
if ($useDb === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo seleccionar la base Vacunacion', 'sqlsrv' => sqlsrv_errors()]);
    exit;
}

if (!sqlsrv_begin_transaction($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar transaccion', 'sqlsrv' => sqlsrv_errors()]); 
    exit;
}

$nextCorteStmt = sqlsrv_query($conn, "SELECT ISNULL(MAX(corte), 0) + 1 AS next_corte FROM Vacunacion.dbo.VacunacionFiebreAmarilla WITH (HOLDLOCK)");
if ($nextCorteStmt === false) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo calcular el nuevo corte', 'sqlsrv' => sqlsrv_errors()]);
    exit;
}
$nextCorteRow = sqlsrv_fetch_array($nextCorteStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($nextCorteStmt);
$corte = $nextCorteRow['next_corte'] ?? null;

if ($corte === null) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo determinar el corte actual']);
    exit;
}

$updateSql = "
UPDATE v
SET v.FechaAplicacionMinisterio = t.FechaUltimaVacuna, v.corte = ?
FROM Vacunacion.dbo.VacunacionFiebreAmarilla v
JOIN [$tempTable] t ON v.TipoDocumento = t.TipoDocumento AND v.NumeroDocumento = t.NumeroDocumento
WHERE v.FechaAplicacionMinisterio IS NULL
";

$stmt = sqlsrv_query($conn, $updateSql, [$corte]);
if ($stmt === false) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar corte',
        'sqlsrv' => sqlsrv_errors(),
        'query' => $updateSql
    ]);
    exit;
}
$actualizados = sqlsrv_rows_affected($stmt);
sqlsrv_free_stmt($stmt);
$intentos = $actualizados;

if (!sqlsrv_commit($conn)) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo confirmar la transaccion', 'sqlsrv' => sqlsrv_errors()]);
    exit;
}

sqlsrv_query($conn, "IF OBJECT_ID('tempdb..$tempTable') IS NOT NULL DROP TABLE $tempTable");
unset($_SESSION['nuevo_corte']);

echo json_encode([
    'success' => true,
    'message' => 'Actualizacion de nuevo corte completada',
    'corte' => $corte,
    'intentos' => $intentos,
    'actualizados' => $actualizados
]);
exit;
