<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config.php'; // ajusta a ../config.php si aplica
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>HISTORIAL</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Select2 (buscador en selects) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
  /* --- Modal archivos --- */
  .file-list { max-height: 280px; overflow:auto; }
  .preview-box { height: 420px; border:1px solid #ddd; border-radius:8px; }
  .preview-box iframe,
  .preview-box img { width:100%; height:100%; border:0; object-fit:contain; }

  /* --- Select2 altura bootstrap --- */
  .select2-container--default .select2-selection--single { height: 38px; }
  .select2-selection__rendered { line-height: 38px !important; }
  .select2-selection__arrow { height: 36px !important; }

  /* --- Wrapper tabla con scroll vertical + barra horizontal fija abajo --- */
/* contenedor general de la tabla */
.table-wrap{
  position: relative;
}

/* contenedor que scrollea vertical */
.table-scroll{
  overflow: auto;
  max-height: 70vh;
  padding-bottom: 18px;  /* 👈 reserva espacio para el scroll horizontal */
}

/* scroll horizontal fijo ABAJO del contenedor */
.x-scrollbar{
  position: sticky;
  bottom: 0;
  left: 0;
  z-index: 10;
  height: 16px;
  overflow-x: auto;
  overflow-y: hidden;
  background: #fff;
  border-top: 1px solid #e5e7eb;
}


  .x-scrollbar-inner{ height: 1px; }

  /* --- COMPACTACIÓN (evita que bootstrap vuelva a estirar la tabla) --- */
  #tablaViaticos{
    width: max-content !important; /* clave: no se estira al 100% */
    table-layout: fixed;           /* anchos estables */
    white-space: nowrap;
    font-size: 12px;
  }

  #tablaViaticos th,
  #tablaViaticos td{
    padding: .20rem .35rem !important;
    vertical-align: middle;
  }

  /* Columna corta: Región */
  #tablaViaticos .col-region{
    width: 55px !important;
    max-width: 55px !important;
    text-align: center;
    white-space: nowrap;
  }

  /* (Opcional recomendado) para que no empujen tanto */
  #tablaViaticos .col-depto{
    width: 140px !important;
    max-width: 140px !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  #tablaViaticos .col-mun{
    width: 160px !important;
    max-width: 160px !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Botones compactos dentro de tabla */
  #tablaViaticos .btn{
    padding: .15rem .35rem !important;
    font-size: 12px !important;
  }

  /* Bloquea el scroll vertical del navegador */
html, body{
  height: 100%;
  overflow: hidden; /* quita el scroll del navegador */
}

/* Deja scroll SOLO en el contenedor de la tabla */
.table-scroll{
  overflow: auto;
  max-height: 70vh; /* ajusta */
}

/* El contenedor principal ocupa la pantalla y no crece */
body .container{
  height: calc(100vh - 20px); /* ajusta si quieres */
  overflow: hidden;
}

/* La tabla puede scrollear dentro */
.table-scroll{
  height: calc(100vh - 320px); /* ajusta según el alto de filtros/encabezado */
  overflow: auto;
}

</style>

</head>
<body class="bg-light">
<div class="container py-4">

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">HISTORICO VIATICOS</h4>

  <a href="../menu.php" class="btn btn-outline-secondary">
    ⬅ Volver al menú
  </a>
</div>

  <!-- Filtros -->
  <form class="card card-body mb-3" method="get" id="formFiltros">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Radicado</label>
        <input type="text" name="rad_via" class="form-control" value="<?= htmlspecialchars($_GET['rad_via'] ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Identificación titular</label>
        <input
          type="text"
          name="numero_identificacion_titular"
          class="form-control"
          value="<?= htmlspecialchars($_GET['numero_identificacion_titular'] ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Región</label>
        <select name="region" id="region" class="form-select select2">
          <option value="">-- Todas --</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Departamento</label>
        <select name="departamento" id="departamento" class="form-select select2">
          <option value="">-- Todos --</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Municipio</label>
        <select name="municipio" id="municipio" class="form-select select2">
          <option value="">-- Todos --</option>
        </select>
      </div>
          <div class="col-md-3">
      <label class="form-label">Pago</label>
      <select name="pago" class="form-select">
        <option value="">-- Todos --</option>
        <option value="pagado"   <?= (($_GET['pago'] ?? '')==='pagado'?'selected':'') ?>>Pagado (con comprobante)</option>
        <option value="sin_pago" <?= (($_GET['pago'] ?? '')==='sin_pago'?'selected':'') ?>>Sin pago (sin comprobante)</option>
      </select>
    </div>
    </div>


    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary">Filtrar</button>
      <a class="btn btn-outline-secondary" href="index.php">Limpiar</a>
    </div>
  </form>

<?php
// ===================== Consulta + PAGINACIÓN (1000 por página) =====================
$params = [];
$wheres = [];

// --- filtros ---
if (!empty($_GET['rad_via'])) {
    $wheres[] = "s.rad_via = ?";
    $params[] = $_GET['rad_via'];
}
if (!empty($_GET['region'])) {
    $wheres[] = "s.region = ?";
    $params[] = $_GET['region'];
}
if (!empty($_GET['departamento'])) {
    $wheres[] = "s.departamento = ?";
    $params[] = $_GET['departamento'];
}
if (!empty($_GET['municipio'])) {
    $wheres[] = "s.municipio = ?";
    $params[] = $_GET['municipio'];
}
if (!empty($_GET['numero_identificacion_titular'])) {
    $wheres[] = "s.numero_identificacion_titular = ?";
    $params[] = $_GET['numero_identificacion_titular'];
}
$pago = trim((string)($_GET['pago'] ?? ''));
if ($pago === 'pagado') {
    // con comprobante
    $wheres[] = "(pvx.comprobante IS NOT NULL AND LTRIM(RTRIM(pvx.comprobante)) <> '')";
}
if ($pago === 'sin_pago') {
    // sin comprobante
    $wheres[] = "(pvx.comprobante IS NULL OR LTRIM(RTRIM(pvx.comprobante)) = '')";
}

// --- paginación ---
$perPage = 1000;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// --- Base SQL (para COUNT y para SELECT) ---
$baseSql = "
WITH ultimo_evento AS (
    SELECT 
        e.id_solicitudes,
        e.radicado,
        e.fecha_estado,
        e.fecha_departamental,
        ROW_NUMBER() OVER(
            PARTITION BY e.radicado
            ORDER BY e.fecha_estado DESC, e.id_solicitudes DESC
        ) AS rn
    FROM gestion_terceros.dbo.evento_solicitudes e
)
FROM gestion_terceros.dbo.solicitudes s
JOIN ultimo_evento ue 
    ON s.radicado = ue.radicado
WHERE ue.rn = 1
" . ($wheres ? " AND " . implode(" AND ", $wheres) : "");

// --- COUNT total ---
$sqlCount = "
;WITH ultimo_evento AS (
    SELECT e.id_solicitudes, e.radicado,
           ROW_NUMBER() OVER(PARTITION BY e.radicado ORDER BY e.fecha_estado DESC, e.id_solicitudes DESC) AS rn
    FROM gestion_terceros.dbo.evento_solicitudes e
),
pv_comp AS (
    SELECT pv.radicado,
           pv.comprobante,
           pv.fecha AS fecha_pago,
           ROW_NUMBER() OVER(
             PARTITION BY pv.radicado
             ORDER BY ISNULL(pv.fecha,'1900-01-01') DESC, pv.id DESC
           ) AS rn
    FROM gestion_terceros.dbo.pagos_viaticos pv
)
SELECT COUNT(1) AS total
FROM gestion_terceros.dbo.solicitudes s
JOIN ultimo_evento ue ON s.radicado = ue.radicado
LEFT JOIN pv_comp pvx ON pvx.radicado = s.rad_via AND pvx.rn = 1
WHERE ue.rn = 1
" . ($wheres ? " AND " . implode(" AND ", $wheres) : "");


$stmtCount = sqlsrv_query($conn, $sqlCount, $params);

if ($stmtCount === false) {
    echo "<div class='alert alert-danger'>Error COUNT: " . htmlspecialchars(print_r(sqlsrv_errors(), true)) . "</div>";
    $totalRows = 0;
} else {
    $rowCount  = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $totalRows = (int)($rowCount['total'] ?? 0);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage; // recalcular

// --- SELECT paginado ---
$sql = "
;WITH ultimo_evento AS (
    SELECT 
        e.id_solicitudes,
        e.radicado,
        e.fecha_estado,
        e.fecha_departamental,
        ROW_NUMBER() OVER(
            PARTITION BY e.radicado
            ORDER BY e.fecha_estado DESC, e.id_solicitudes DESC
        ) AS rn
    FROM gestion_terceros.dbo.evento_solicitudes e
),
pv_comp AS (
    SELECT pv.radicado,
           pv.comprobante,
           pv.fecha AS fecha_pago,
           ROW_NUMBER() OVER(
             PARTITION BY pv.radicado
             ORDER BY ISNULL(pv.fecha,'1900-01-01') DESC, pv.id DESC
           ) AS rn
    FROM gestion_terceros.dbo.pagos_viaticos pv
)
SELECT 
    s.radicado,
    s.rad_via,
    s.numero_identificacion,
    s.url_drive,
    s.apr_departamental,
    ue.fecha_departamental,
    s.proceso,
    ue.fecha_estado,
    s.region,
    s.departamento,
    s.municipio,
    pvx.comprobante,
    pvx.fecha_pago
FROM gestion_terceros.dbo.solicitudes s
JOIN ultimo_evento ue ON s.radicado = ue.radicado
LEFT JOIN pv_comp pvx ON pvx.radicado = s.rad_via AND pvx.rn = 1
WHERE ue.rn = 1
" . ($wheres ? " AND " . implode(" AND ", $wheres) : "") . "
ORDER BY s.rad_via_num, s.rad_via
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY;
";


$paramsPage = array_merge($params, [$offset, $perPage]);

$stmt = sqlsrv_query($conn, $sql, $paramsPage);

if ($stmt === false) {
    echo "<div class='alert alert-danger'>Error de consulta: " . htmlspecialchars(print_r(sqlsrv_errors(), true)) . "</div>";
} else {

    // ---------- Paginación (ARRIBA) ----------
    $query = $_GET;
    unset($query['page']);

    $makeUrl = function($p) use ($query) {
        $query['page'] = $p;
        return 'index.php?' . http_build_query($query);
    };

    $prev  = max(1, $page - 1);
    $next  = min($totalPages, $page + 1);
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    ?>
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="text-muted small">
        Mostrando <?= ($totalRows ? min($totalRows, $offset+1) : 0) ?>–<?= ($totalRows ? min($totalRows, $offset+$perPage) : 0) ?>
        de <?= (int)$totalRows ?> registros
      </div>

      <nav>
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= ($page<=1?'disabled':'') ?>">
            <a class="page-link" href="<?= htmlspecialchars($makeUrl($prev)) ?>">«</a>
          </li>

          <?php if ($start > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($makeUrl(1)) ?>">1</a></li>
            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
          <?php endif; ?>

          <?php for ($p=$start; $p<=$end; $p++): ?>
            <li class="page-item <?= ($p==$page?'active':'') ?>">
              <a class="page-link" href="<?= htmlspecialchars($makeUrl($p)) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages-1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($makeUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
          <?php endif; ?>

          <li class="page-item <?= ($page>=$totalPages?'disabled':'') ?>">
            <a class="page-link" href="<?= htmlspecialchars($makeUrl($next)) ?>">»</a>
          </li>
        </ul>
      </nav>
    </div>

<div class="table-wrap">
  <!-- Scroll real (tabla) -->
  <div class="table-scroll" id="tableScroll">
    <table class="table table-sm table-striped align-middle mb-0" id="tablaViaticos">
          <thead class="table-light">
            <tr>
              <th>radicado</th>
              <th>Identificación</th>
              <th class="col-region">Región</th>
              <th class="col-depto">Departamento</th>
              <th class="col-mun">Municipio</th>
              <th>Carpeta (url_drive)</th>
              <th>calificacion departamental</th>
              <th>fecha de calificacion</th>
              <th>calificacion nacional</th>
              <th>fecha de calificacion</th>
              <th>Observaciones</th>
              <th>Acciones</th>
              <th>Comprobante</th>
              <th>Fecha de pago</th>

            </tr>
          </thead>
          <tbody>
          <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
              $radVia  = $row['rad_via'];
              $url     = $row['url_drive'];

              $fechaEs = $row['fecha_departamental'];
              $fechaEsTxt = $fechaEs instanceof DateTime ? $fechaEs->format('Y-m-d') : htmlspecialchars((string)$fechaEs);

              $fechaEs2 = $row['fecha_estado'];
              $fechaEsTxt2 = $fechaEs2 instanceof DateTime ? $fechaEs2->format('Y-m-d') : htmlspecialchars((string)$fechaEs2);
          ?>
            <tr>
              <td><?= htmlspecialchars((string)$radVia) ?></td>
              <td><?= htmlspecialchars((string)$row['numero_identificacion']) ?></td>
              <td class="col-region"><?= htmlspecialchars((string)$row['region']) ?></td>
              <td class="col-depto"><?= htmlspecialchars((string)$row['departamento']) ?></td>
              <td class="col-mun"><?= htmlspecialchars((string)$row['municipio']) ?></td>

              <td>
                <?php if (!empty($url)): ?>
                  <button
                    class="btn btn-sm btn-outline-primary ver-carpeta"
                    data-path="<?= htmlspecialchars((string)$url) ?>"
                    data-rad="<?= htmlspecialchars((string)$radVia) ?>"
                    type="button">
                    Abrir
                  </button>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)$row['apr_departamental']) ?></td>
              <td><?= $fechaEsTxt ?></td>
              <td><?= htmlspecialchars((string)$row['proceso']) ?></td>
              <td><?= $fechaEsTxt2 ?></td>
              <td>
                <button
                  class="btn btn-sm btn-outline-dark ver-observaciones"
                  data-radicado="<?= htmlspecialchars((string)$row['radicado']) ?>"
                  type="button">
                  Ver
                </button>
              </td>
              <td>
              <?php
                $rechDep   = strtoupper(trim((string)$row['apr_departamental'])) === 'RECHAZADO';
                $rechPro   = strtoupper(trim((string)$row['proceso'])) === 'RECHAZADO';
                $isSubDep  = strtoupper(trim((string)$row['apr_departamental'])) === 'SUBSANACION';
                $isSubNac  = strtoupper(trim((string)$row['proceso'])) === 'SUBSANACION';
                $esSub     = $isSubDep || $isSubNac;

                if ($rechDep || $rechPro): ?>
                  <button type="button" class="btn btn-sm btn-warning btn-objetar me-1"
                          data-radicado="<?= htmlspecialchars((string)$row['radicado']) ?>">
                    Objetar
                  </button>
                <?php endif; ?>

                <?php if ($esSub && !empty($row['url_drive'])): ?>
                  <button type="button" class="btn btn-sm btn-info btn-corregir"
                          data-radicado="<?= htmlspecialchars((string)$row['radicado']) ?>"
                          data-path="<?= htmlspecialchars((string)$row['url_drive']) ?>">
                    Corregir
                  </button>
                <?php else: ?>
                  <?php if (!$rechDep && !$rechPro): ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
                <?php
    $comp = trim((string)($row['comprobante'] ?? ''));
    $fechaPago = $row['fecha_pago'] ?? null;
    $fechaPagoTxt = ($fechaPago instanceof DateTime) ? $fechaPago->format('Y-m-d') : htmlspecialchars((string)$fechaPago);
  ?>
  <td class="nowrap">
    <?= htmlspecialchars($comp) ?>
    <?php if ($comp !== ''): ?>
      <button type="button"
              class="btn btn-sm btn-outline-success btn-comprobante ms-1"
              data-comp="<?= htmlspecialchars($comp) ?>">
        Ver
      </button>
    <?php else: ?>
      <span class="text-muted">—</span>
    <?php endif; ?>
  </td>
  <td><?= $fechaPagoTxt ?: '<span class="text-muted">—</span>' ?></td>

            </tr>
          <?php endwhile; ?>
          </tbody>
    </table>
  </div>

  <div class="x-scrollbar" id="xScrollbar" aria-hidden="true">
    <div class="x-scrollbar-inner" id="xScrollbarInner"></div>
  </div>
</div>
<?php
} // <-- CIERRE CORRECTO del else de $stmt ok
?>


</div>

<!-- Modal Archivos -->
<div class="modal fade" id="modalArchivos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Archivos de <span id="modalRad"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="card">
              <div class="card-header py-2">
                <small class="text-muted mb-0">Lista de archivos</small>
              </div>
              <div class="card-body file-list p-0">
                <ul class="list-group list-group-flush" id="listaArchivos"></ul>
              </div>
            </div>
          </div>
          <div class="col-md-8">
            <div class="preview-box d-flex align-items-center justify-content-center" id="previewBox">
              <div class="text-muted">Selecciona un archivo para previsualizar…</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted" id="modalPath"></small>
        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="button" id="btnOpenUpload">Subir archivos</button>
          <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Modal selección archivos -->
<div class="modal fade" id="modalSeleccionArchivos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Subir archivos</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2">Destino: <span id="uploadPathText">-</span></div>
        <input class="form-control" type="file" id="uploadInputModal" multiple>
        <div class="mt-2">
          <ul class="list-group" id="uploadList"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="button" id="btnDoUpload" disabled>Subir</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal confirmar eliminar -->
<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Eliminar archivo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">¿Seguro deseas eliminar el archivo <strong id="delFileName"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger" type="button" id="btnDeleteConfirm">Eliminar</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal Observaciones -->
<div class="modal fade" id="modalObservaciones" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Observaciones — Radicado</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="obsContainer">
          <div class="text-muted">Cargando observaciones…</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal Objetar -->
<div class="modal fade" id="modalObjetar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formObjetar">
      <div class="modal-header">
        <h6 class="modal-title">Objetar — Radicado: <span id="objRadicado"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="radicado" id="objRadicadoInput">
        <div class="mb-2">
          <label class="form-label">Comentario de objeción</label>
          <textarea class="form-control" name="comentario" id="objComentario" rows="5" required></textarea>
        </div>
        <div class="small text-muted">
          Se registrará un nuevo evento con la objeción y se actualizará el estado a <b>Objetado</b>.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar objeción</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal Corregir -->
<div class="modal fade" id="modalCorregir" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formCorregir" enctype="multipart/form-data">
      <div class="modal-header">
        <h6 class="modal-title">Corregir — Radicado: <span id="corrRadicadoLbl"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="radicado" id="corrRadicadoInput">
        <input type="hidden" name="url_drive" id="corrPathInput">

        <div class="mb-2">
          <label class="form-label">Observación de corrección <span class="text-danger">*</span></label>
          <textarea class="form-control" name="observacion" id="corrObservacion" rows="4" maxlength="500" required></textarea>
          <div class="form-text">Máx. 500 caracteres.</div>
        </div>

        <div class="mb-2">
          <label class="form-label">Adjuntar archivos</label>
          <input class="form-control" type="file" id="corrFiles" name="files[]" multiple>
          <div class="form-text">Puedes seleccionar varios archivos.</div>
        </div>

        <div class="mt-2">
          <ul id="corrFileList" class="list-group"></ul>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar corrección</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal Comprobante -->
<div class="modal fade" id="modalComprobante" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Comprobante: <span id="cmpLbl"></span></h6>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary" id="cmpDownload" href="#" target="_blank" rel="noopener">Descargar</a>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
      </div>
      <div class="modal-body p-0" style="height:75vh;">
        <iframe id="cmpFrame" src="about:blank" style="width:100%; height:100%; border:0;"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>

  
// Init Select2 con buscador
$('.select2').select2({ placeholder: '', width: '100%', allowClear: true });

// ---------- Helpers para cargar opciones ----------
function setOptions($select, items, currentValue) {
  $select.empty().append(new Option('-- Todos --', ''));
  items.forEach(v => $select.append(new Option(v, v)));
  if (currentValue) $select.val(currentValue).trigger('change.select2');
}

// ---------- Carga inicial de los 3 combos ----------
const selRegion = $('#region');
const selDepto  = $('#departamento');
const selMun    = $('#municipio');

const currentRegion = <?= json_encode($_GET['region'] ?? '') ?>;
const currentDep    = <?= json_encode($_GET['departamento'] ?? '') ?>;
const currentMun    = <?= json_encode($_GET['municipio'] ?? '') ?>;

Promise.all([
  fetch('filtros_regiones.php').then(r=>r.json()),
  fetch('filtros_departamentos.php').then(r=>r.json()), // sin filtro: todos los dptos
  fetch('filtros_municipios.php').then(r=>r.json())     // sin filtro: todos los mpios
]).then(([regiones, departamentos, municipios]) => {
  setOptions(selRegion, regiones, currentRegion);
  setOptions(selDepto,  departamentos, currentDep);
  setOptions(selMun,    municipios,   currentMun);
});

// ---------- Encadenamiento flexible ----------
// Al cambiar región: recargar dpto/mun con filtro por región
selRegion.on('change', () => {
  const region = selRegion.val();

  // Departamentos (filtrados por región si hay)
  let urlD = new URL('filtros_departamentos.php', location.href);
  if (region) urlD.searchParams.set('region', region);

  fetch(urlD).then(r=>r.json()).then(deps => {
    setOptions(selDepto, deps, currentDep && selDepto.find(`option[value="${currentDep}"]`).length ? currentDep : '');
  });

  // Municipios (filtrados por región si hay, sin dpto aún)
  let urlM = new URL('filtros_municipios.php', location.href);
  if (region) urlM.searchParams.set('region', region);

  fetch(urlM).then(r=>r.json()).then(muns => {
    setOptions(selMun, muns, currentMun && selMun.find(`option[value="${currentMun}"]`).length ? currentMun : '');
  });
});

// Al cambiar departamento: cargar municipios filtrados por región/departamento (si alguno existe)
selDepto.on('change', () => {
  const region = selRegion.val();
  const dep    = selDepto.val();

  let urlM = new URL('filtros_municipios.php', location.href);
  if (region) urlM.searchParams.set('region', region);
  if (dep)    urlM.searchParams.set('departamento', dep);

  fetch(urlM).then(r=>r.json()).then(muns => {
    setOptions(selMun, muns, '');
  });
});

// ---------- MODAL ARCHIVOS ----------
let bsModal = new bootstrap.Modal(document.getElementById('modalArchivos'));
let modalUpload = new bootstrap.Modal(document.getElementById('modalSeleccionArchivos'));
let modalDelete = new bootstrap.Modal(document.getElementById('modalConfirmDelete'));
const listaArchivosEl = document.getElementById('listaArchivos');
const previewBoxEl = document.getElementById('previewBox');
const uploadInputModal = document.getElementById('uploadInputModal');
const uploadListEl = document.getElementById('uploadList');
const btnOpenUpload = document.getElementById('btnOpenUpload');
const btnDoUpload = document.getElementById('btnDoUpload');
const uploadPathText = document.getElementById('uploadPathText');
const delFileNameEl = document.getElementById('delFileName');
const btnDeleteConfirm = document.getElementById('btnDeleteConfirm');
let currentFilesPath = '';
let deleteTargetB64 = '';

function resetUploadSelection() {
  if (uploadInputModal) uploadInputModal.value = '';
  if (uploadListEl) uploadListEl.innerHTML = '<li class="list-group-item text-muted">Sin archivos seleccionados.</li>';
  if (btnDoUpload) btnDoUpload.disabled = true;
}

function cargarArchivos(path) {
  const url = new URL('listar_archivos.php', location.href);
  url.searchParams.set('path', path);

  if (listaArchivosEl) listaArchivosEl.innerHTML = '<li class="list-group-item">Cargando…</li>';
  if (previewBoxEl) previewBoxEl.innerHTML = '<div class="text-muted">Selecciona un archivo para previsualizar…</div>';

  return fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!listaArchivosEl) return;
      listaArchivosEl.innerHTML = '';
      if (!data.files || data.files.length === 0) {
        listaArchivosEl.innerHTML = '<li class="list-group-item text-muted">Sin archivos</li>';
        return;
      }
      data.files.forEach(f => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = `
        <span class="cursor-pointer" data-preview="${f.previewUrl}" data-ext="${f.ext}">${f.name}</span>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-secondary" href="${f.downloadUrl}">Descargar</a>
          <button class="btn btn-sm btn-outline-danger btn-delete-file" type="button" data-b64="${f.b64 || ''}" data-name="${f.name}">✕</button>
        </div>
      `;
        li.querySelector('span').addEventListener('click', () => {
          renderPreview(f.previewUrl, f.ext);
        });
        li.querySelector('.btn-delete-file').addEventListener('click', (evt) => {
          deleteTargetB64 = evt.currentTarget.dataset.b64 || '';
          const nombre = evt.currentTarget.dataset.name || '';
          if (delFileNameEl) delFileNameEl.textContent = nombre || '(sin nombre)';
          modalDelete.show();
        });
        listaArchivosEl.appendChild(li);
      });
    })
    .catch(() => {
      if (listaArchivosEl) {
        listaArchivosEl.innerHTML = '<li class="list-group-item text-danger">No se pudo cargar la lista.</li>';
      }
    });
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.ver-carpeta');
  if (!btn) return;

  const path = btn.dataset.path || '';
  const rad  = btn.dataset.rad || '';
  document.getElementById('modalRad').textContent = rad || '-';
  document.getElementById('modalPath').textContent = path || '-';

  currentFilesPath = path;
  resetUploadSelection();
  cargarArchivos(path);

  bsModal.show();
});

function renderPreview(url, ext) {
  const box = document.getElementById('previewBox');
  ext = (ext || '').toLowerCase();
  if (['png','jpg','jpeg','gif','webp','bmp'].includes(ext)) {
    box.innerHTML = `<img src="${url}" alt="preview">`;
  } else if (ext === 'pdf') {
    box.innerHTML = `<iframe src="${url}"></iframe>`;
  } else if (['txt','csv','log','md','json','xml'].includes(ext)) {
    box.innerHTML = `<iframe src="${url}"></iframe>`;
  } else {
    box.innerHTML = `<div class="p-3">No hay vista previa para .${ext}. Use descargar.</div>`;
  }
}

btnOpenUpload?.addEventListener('click', () => {
  if (!currentFilesPath) { alert('No hay ruta destino.'); return; }
  resetUploadSelection();
  if (uploadPathText) uploadPathText.textContent = currentFilesPath;
  modalUpload.show();
});

uploadInputModal?.addEventListener('change', (e) => {
  const files = Array.from(e.target.files || []);
  if (!uploadListEl || !btnDoUpload) return;
  uploadListEl.innerHTML = '';
  if (files.length === 0) {
    uploadListEl.innerHTML = '<li class="list-group-item text-muted">Sin archivos seleccionados.</li>';
    btnDoUpload.disabled = true;
    return;
  }
  files.forEach(f => {
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center';
    li.textContent = `${f.name} (${Math.ceil(f.size/1024)} KB)`;
    uploadListEl.appendChild(li);
  });
  btnDoUpload.disabled = false;
});

btnDoUpload?.addEventListener('click', async () => {
  const files = Array.from(uploadInputModal?.files || []);
  if (!currentFilesPath) { alert('No hay ruta destino.'); return; }
  if (files.length === 0) { alert('Selecciona archivos antes de subir.'); return; }

  btnDoUpload.disabled = true;
  const originalText = btnDoUpload.textContent;
  btnDoUpload.textContent = 'Subiendo...';

  const fd = new FormData();
  fd.append('path', currentFilesPath);
  files.forEach(f => fd.append('files[]', f));

  try {
    const resp = await fetch('subir_archivos.php', { method: 'POST', body: fd });
    const data = await resp.json();
    if (!data.ok) {
      alert(data.msg || 'No se pudo subir los archivos.');
    } else {
      alert(data.msg || 'Archivos subidos.');
      resetUploadSelection();
      modalUpload.hide();
      if (currentFilesPath) cargarArchivos(currentFilesPath);
    }
  } catch (err) {
    console.error(err);
    alert('Error de red al subir archivos.');
  } finally {
    btnDoUpload.textContent = originalText;
    btnDoUpload.disabled = false;
  }
});

btnDeleteConfirm?.addEventListener('click', async () => {
  if (!deleteTargetB64) { alert('No hay archivo seleccionado.'); return; }
  btnDeleteConfirm.disabled = true;
  const originalText = btnDeleteConfirm.textContent;
  btnDeleteConfirm.textContent = 'Eliminando...';

  const fd = new FormData();
  fd.append('b64', deleteTargetB64);

  try {
    const resp = await fetch('eliminar_archivo.php', { method: 'POST', body: fd });
    const data = await resp.json();
    if (!data.ok) {
      alert(data.msg || 'No se pudo eliminar el archivo.');
    } else {
      alert(data.msg || 'Archivo eliminado.');
      deleteTargetB64 = '';
      modalDelete.hide();
      if (currentFilesPath) cargarArchivos(currentFilesPath);
    }
  } catch (err) {
    console.error(err);
    alert('Error de red al eliminar.');
  } finally {
    btnDeleteConfirm.textContent = originalText;
    btnDeleteConfirm.disabled = false;
  }
});

// ---------- MODAL OBSERVACIONES ----------
let modalObs = new bootstrap.Modal(document.getElementById('modalObservaciones'));

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.ver-observaciones');
  if (!btn) return;

  const radicado = btn.dataset.radicado || '';
  document.getElementById('obsRadicado').textContent = radicado;
  const box = document.getElementById('obsContainer');
  box.innerHTML = '<div class="text-muted">Cargando observaciones…</div>';

  const url = new URL('observaciones.php', location.href);
  url.searchParams.set('radicado', radicado);

  fetch(url).then(r => r.json()).then(data => {
    // data = { ok: true, items: [...] }
    if (!data.ok) {
      box.innerHTML = `<div class="alert alert-warning">Sin observaciones</div>`;
      modalObs.show();
      return;
    }
    if (!data.items || data.items.length === 0) {
      box.innerHTML = `<div class="alert alert-info">No hay observaciones registradas para este radicado.</div>`;
      modalObs.show();
      return;
    }

      // Render
  // Render
  const html = data.items.map(it => {

const tipoBadgeMap = {
  'DEPARTAMENTAL':      '<span class="badge text-bg-success">Departamental</span>',
  'OBJECION':           '<span class="badge text-bg-danger">Objeción</span>',
  'RESPUESTA_OBJECION': '<span class="badge text-bg-warning text-dark">Respuesta objeción</span>',
  'NACIONAL':           '<span class="badge text-bg-primary">Nacional</span>',
  'CORRECCION':         '<span class="badge text-bg-secondary">Corrección</span>',
};


    const tipoBadge = tipoBadgeMap[it.tipo]
      || `<span class="badge text-bg-secondary">${it.tipo || 'Otro'}</span>`;

    const fechaTxt = it.fecha || '';
    const obsTxt   = (it.observacion || '').replace(/\n/g,'<br>');

    return `
      <div class="border rounded p-2 mb-2">
        <div class="d-flex justify-content-between">
          <div>${tipoBadge}</div>
          <small class="text-muted">${fechaTxt}</small>
        </div>
        <div class="mt-2">${obsTxt || '<em class="text-muted">—</em>'}</div>
      </div>
    `;
  }).join('');

    box.innerHTML = html;
    modalObs.show();
  }).catch(() => {
    box.innerHTML = `<div class="alert alert-danger">No fue posible cargar las observaciones.</div>`;
    modalObs.show();
  });
});
// ------- Modal Objetar -------
let modalObjetar = new bootstrap.Modal(document.getElementById('modalObjetar'));

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-objetar');
  if (!btn) return;
  const radicado = btn.dataset.radicado || '';
  document.getElementById('objRadicado').textContent = radicado;
  document.getElementById('objRadicadoInput').value = radicado;
  document.getElementById('objComentario').value = '';
  modalObjetar.show();
});

document.getElementById('formObjetar').addEventListener('submit', async (e) => {
  e.preventDefault();
  const radicado  = document.getElementById('objRadicadoInput').value.trim();
  const comentario = document.getElementById('objComentario').value.trim();
  if (!radicado || !comentario) return;

  const resp = await fetch('objetar.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ radicado, comentario })
  });
  const data = await resp.json();

  if (!data.ok) {
    alert('No se pudo registrar la objeción:\n' + (data.msg || 'Error'));
    return;
  }
  modalObjetar.hide();
  // Refresca la página para ver estados actualizados
  location.reload();
});


// ------- Modal Corregir -------
let modalCorregir = new bootstrap.Modal(document.getElementById('modalCorregir'));

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-corregir');
  if (!btn) return;

  const radicado = btn.dataset.radicado || '';
  const path     = btn.dataset.path || '';

  document.getElementById('corrRadicadoLbl').textContent = radicado;
  document.getElementById('corrRadicadoInput').value     = radicado;
  document.getElementById('corrPathInput').value         = path;
  document.getElementById('corrObservacion').value       = '';
  document.getElementById('corrFileList').innerHTML      = '';
  document.getElementById('corrFiles').value             = '';

  modalCorregir.show();
});

// Mostrar lista de archivos seleccionados
document.getElementById('corrFiles').addEventListener('change', (e) => {
  const ul = document.getElementById('corrFileList');
  ul.innerHTML = '';
  const files = Array.from(e.target.files || []);
  if (files.length === 0) {
    ul.innerHTML = '<li class="list-group-item text-muted">Sin archivos seleccionados.</li>';
    return;
  }
  files.forEach(f => {
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center';
    li.textContent = f.name + ' (' + Math.ceil(f.size/1024) + ' KB)';
    ul.appendChild(li);
  });
});

// Enviar corrección
document.getElementById('formCorregir').addEventListener('submit', async (e) => {
  e.preventDefault();

  const form = e.currentTarget;
  const fd   = new FormData(form);

  // Validación mínima
  const obs = (fd.get('observacion') || '').toString().trim();
  if (!obs) { alert('La observación es obligatoria.'); return; }

  try {
    const resp = await fetch('correccion.php', {
      method: 'POST',
      body: fd
    });
    const data = await resp.json();

    if (!data.ok) {
      alert('No se pudo guardar la corrección:\n' + (data.msg || 'Error'));
      return;
    }

    modalCorregir.hide();
    alert('Corrección registrada correctamente.');
    // refresca lista si quieres
    location.reload();
  } catch (err) {
    console.error(err);
    alert('Error de red al enviar la corrección.');
  }
});
let modalComprobante = new bootstrap.Modal(document.getElementById('modalComprobante'));

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-comprobante');
  if (!btn) return;

  const comp = btn.dataset.comp || '';
  if (!comp) return;

  document.getElementById('cmpLbl').textContent = comp;

  // Igual que tu vista pequeña (inline para iframe)
  const urlInline = 'ver_comprobante.php?comp=' + encodeURIComponent(comp) + '&find=1&inline=1';
  const urlDown   = 'ver_comprobante.php?comp=' + encodeURIComponent(comp) + '&find=1';

  document.getElementById('cmpFrame').src = urlInline;
  document.getElementById('cmpDownload').href = urlDown;

  modalComprobante.show();
});

// limpiar al cerrar
document.getElementById('modalComprobante').addEventListener('hidden.bs.modal', () => {
  document.getElementById('cmpFrame').src = 'about:blank';
});
(function(){
  const tableScroll = document.getElementById('tableScroll');
  const table       = document.getElementById('tablaViaticos');
  const xScrollbar  = document.getElementById('xScrollbar');
  const inner       = document.getElementById('xScrollbarInner');

  if (!tableScroll || !table || !xScrollbar || !inner) return;

  function syncWidth(){
    inner.style.width = table.scrollWidth + 'px';
  }

  let lock = false;

  tableScroll.addEventListener('scroll', () => {
    if (lock) return;
    lock = true;
    xScrollbar.scrollLeft = tableScroll.scrollLeft;
    lock = false;
  });

  xScrollbar.addEventListener('scroll', () => {
    if (lock) return;
    lock = true;
    tableScroll.scrollLeft = xScrollbar.scrollLeft;
    lock = false;
  });

  window.addEventListener('resize', syncWidth);
syncWidth();
setTimeout(syncWidth, 300);
setTimeout(syncWidth, 900);

})();

</script>
</body>
</html>
