<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

session_start();

$radicado = trim((string)($_GET['radicado'] ?? ''));
if ($radicado === '') {
  echo json_encode(['ok'=>false,'msg'=>'Falta radicado']); exit;
}
$sql = "
;WITH E AS (
  SELECT
    id_solicitudes,
    CAST(radicado AS VARCHAR(50)) AS radicado,
    evento,
    fecha_solicitud,              -- ✅ NUEVO (para marcar ANTIGUO)
    fecha_estado,
    observacion,
    fecha_departamental,
    observacion_departamental,
    fecha_objecion,
    observacion_objecion,
    correccion_observacion,
    fecha_correccion,
    estado_proceso
  FROM gestion_terceros.dbo.evento_solicitudes
  WHERE CAST(radicado AS VARCHAR(50)) = ?
),
K AS (
  SELECT
    MIN(CASE WHEN evento='respuesta_objecion' THEN id_solicitudes END) AS id_resp,
    MIN(CASE WHEN observacion_departamental IS NOT NULL
              AND LTRIM(RTRIM(observacion_departamental))<>'' 
              AND evento NOT IN ('OBJECION','respuesta_objecion')
        THEN id_solicitudes END) AS id_dep_base
  FROM E
),
N AS (
  SELECT
    'DEPARTAMENTAL' AS tipo,
    e.fecha_departamental AS fecha,
    e.observacion_departamental AS observacion,
    CAST((SELECT id_dep_base FROM K) AS float) + 0.01 AS sort_key
  FROM E e
  CROSS JOIN K
  WHERE e.id_solicitudes = K.id_dep_base

  UNION ALL

  SELECT
    'OBJECION' AS tipo,
    e.fecha_objecion AS fecha,
    e.observacion_objecion AS observacion,
    CAST(e.id_solicitudes AS float) + 0.20 AS sort_key
  FROM E e
  WHERE e.evento = 'OBJECION'
    AND e.observacion_objecion IS NOT NULL
    AND LTRIM(RTRIM(e.observacion_objecion)) <> ''

  UNION ALL

  SELECT
    'RESPUESTA_OBJECION' AS tipo,
    e.fecha_departamental AS fecha,
    e.observacion_departamental AS observacion,
    CAST(e.id_solicitudes AS float) + 0.30 AS sort_key
  FROM E e
  WHERE e.evento = 'respuesta_objecion'
    AND e.observacion_departamental IS NOT NULL
    AND LTRIM(RTRIM(e.observacion_departamental)) <> ''

  UNION ALL

  -- ✅ 4) Nacional (calificacion_nacional) -> si fecha_solicitud <= 2025-12-17, marcar como ANTIGUO
  SELECT
    'NACIONAL' AS tipo,
    e.fecha_estado AS fecha,
    CASE
      WHEN e.fecha_solicitud IS NOT NULL
       AND CAST(e.fecha_solicitud AS date) <= CONVERT(date,'2025-12-17')
        THEN CONCAT('ANTIGUO: ', e.observacion)
      ELSE e.observacion
    END AS observacion,
    CAST(e.id_solicitudes AS float) + 0.40 AS sort_key
  FROM E e
  WHERE e.evento = 'calificacion_nacional'
    AND e.observacion IS NOT NULL
    AND LTRIM(RTRIM(e.observacion)) <> ''

  UNION ALL

-- ✅ 4b) Nacional desde creacion_viaticos -> misma regla ANTIGUO (incluye Rechazado / Aprobado / Subsanacion)
SELECT
  'NACIONAL' AS tipo,
  e.fecha_estado AS fecha,
  CASE
    WHEN e.fecha_solicitud IS NOT NULL
     AND CAST(e.fecha_solicitud AS date) <= CONVERT(date,'2025-12-17')
      THEN CONCAT('ANTIGUO: ', e.observacion)
    ELSE e.observacion
  END AS observacion,
  CAST(e.id_solicitudes AS float) + 0.41 AS sort_key
FROM E e
WHERE e.evento = 'creacion_viaticos'
  AND e.observacion IS NOT NULL
  AND LTRIM(RTRIM(e.observacion)) <> ''


  UNION ALL

  SELECT
    'CORRECCION' AS tipo,
    e.fecha_correccion AS fecha,
    e.correccion_observacion AS observacion,
    CAST(e.id_solicitudes AS float) + 0.50 AS sort_key
  FROM E e
  WHERE e.correccion_observacion IS NOT NULL
    AND LTRIM(RTRIM(e.correccion_observacion)) <> ''
)
SELECT tipo, fecha, observacion
FROM N
ORDER BY sort_key ASC;
";


$stmt = sqlsrv_query($conn, $sql, [$radicado]);
if ($stmt === false) {
  echo json_encode(['ok'=>false,'msg'=>'Error SQL','err'=>sqlsrv_errors()]); exit;
}

$items = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $fecha = $r['fecha'];
  if ($fecha instanceof DateTime) $fechaTxt = $fecha->format('Y-m-d H:i:s');
  else $fechaTxt = $fecha ? (string)$fecha : null;

  $items[] = [
    'tipo' => (string)$r['tipo'],
    'fecha' => $fechaTxt,
    'observacion' => (string)$r['observacion'],
  ];
}
sqlsrv_free_stmt($stmt);

echo json_encode(['ok'=>true,'items'=>$items]);
