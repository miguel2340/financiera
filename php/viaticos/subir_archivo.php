<?php
session_start();
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp  = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

/* ============ Utilidades ============ */
// Sanitiza componente de carpeta (Windows-safe)
function sanitizarNombre($s) {
    $s = str_replace('.', '', $s);                                     // sin puntos
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);                        // acentos→ASCII
    $s = preg_replace('/[\\\\\/\:\*\?\"\<\>\|]/', '', $s);             // inválidos Win
    $s = preg_replace('/[^A-Za-z0-9 _-]/', '', $s);                    // resto raros
    $s = trim(preg_replace('/\s+/', ' ', $s));                         // espacios
    return $s === '' ? 'SIN_NOMBRE' : $s;
}
// Sanitiza nombre de archivo (sin rutas, sin caracteres peligrosos)
function sanitizarArchivo($s) {
    $s = basename($s);                                                 // sin path
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = preg_replace('/[\\\\\/\:\*\?\"\<\>\|\x00-\x1F]/', '', $s);    // inválidos + control
    $s = preg_replace('/\s+/', ' ', trim($s));
    // evita nombres vacíos o solo extensión
    if ($s === '' || $s === '.' || $s === '..') $s = 'archivo';
    return $s;
}
function ensureDir(string $path): void {
    if (is_dir($path)) return;
    if (!@mkdir($path, 0777, true)) {
        $e = error_get_last();
        throw new RuntimeException("No se pudo crear '$path': " . ($e['message'] ?? 'error desconocido'));
    }
}
// Genera nombre único si ya existe
function destinoUnico(string $dir, string $nombre): string {
    $ruta = $dir . DIRECTORY_SEPARATOR . $nombre;
    if (!file_exists($ruta)) return $ruta;
    $pi = pathinfo($nombre);
    $base = $pi['filename'] ?? 'archivo';
    $ext  = isset($pi['extension']) && $pi['extension'] !== '' ? '.' . $pi['extension'] : '';
    $n = 1;
    do {
        $ruta = $dir . DIRECTORY_SEPARATOR . $base . " ($n)" . $ext;
        $n++;
    } while (file_exists($ruta));
    return $ruta;
}

/* ============ Autenticación ============ */
if (!isset($_SESSION['usuario_autenticado']) || !isset($_SESSION['nombre_usuario'])) {
    header("Location: ../../inicio_sesion.php");
    exit;
}

$usuario       = $_SESSION['nombre_usuario'];
$departamento  = $_GET['departamento']   ?? '';
$identificacion= $_GET['identificacion'] ?? '';

if (empty($departamento) || empty($identificacion)) {
    $error = "Faltan datos en la URL.";
} else {
    // Mostrar en la UI el original, pero usar SANITIZADO para el disco
    $depSan  = sanitizarNombre($departamento);
    $idSan   = sanitizarNombre($identificacion);


    $basePath = "E:\\FINANCIERA\\{$depSan}\\{$idSan}";
    try {
        // Verificar/crear carpetas
        $parent = dirname($basePath);
        if (!is_dir($parent)) ensureDir($parent);
        if (!is_writable($parent)) {
            throw new RuntimeException("Sin permisos de escritura en '$parent'. Ajusta NTFS.");
        }
        ensureDir($basePath);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
            $fechaCarpeta = date("Ymd_His");
            $pathFinal    = $basePath . DIRECTORY_SEPARATOR . $fechaCarpeta;
            ensureDir($pathFinal);

            $ok = 0; $fail = 0; $dbFail = 0; $mensajes = [];
            $rutaSQL = str_replace("\\", "/", $pathFinal);

            // Recorremos los inputs de archivo
            $names  = $_FILES['archivo']['name']  ?? [];
            $tmp    = $_FILES['archivo']['tmp_name'] ?? [];
            $errs   = $_FILES['archivo']['error'] ?? [];

            foreach ($names as $i => $fileName) {
                $fileName = $fileName ?? '';
                $err      = $errs[$i]  ?? UPLOAD_ERR_NO_FILE;

                // Saltar inputs sin archivo
                if ($err === UPLOAD_ERR_NO_FILE || $fileName === '' || !isset($tmp[$i])) {
                    continue;
                }
                if ($err !== UPLOAD_ERR_OK) {
                    $fail++; $mensajes[] = "Falló la carga de '{$fileName}' (error={$err}).";
                    continue;
                }

                $tmpName = $tmp[$i];
                if (!is_uploaded_file($tmpName)) {
                    $fail++; $mensajes[] = "El archivo '{$fileName}' no es una carga válida.";
                    continue;
                }

                $nombreLimpio = sanitizarArchivo($fileName);
                $destino      = destinoUnico($pathFinal, $nombreLimpio);

                if (!@move_uploaded_file($tmpName, $destino)) {
                    $e = error_get_last();
                    $fail++; $mensajes[] = "No se pudo mover '{$fileName}': " . ($e['message'] ?? 'error desconocido');
                    continue;
                }

                // Registrar en BD (1 registro por archivo subido)
                $fecha  = date("Y-m-d H:i:s");
                $sql    = "INSERT INTO archivos_subidos (usuario, identificacion, fecha_subida, ruta_archivo)
                           VALUES (?, ?, ?, ?)";
                $params = [$usuario, $identificacion, $fecha, $rutaSQL];
                $stmt   = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    $dbFail++; $mensajes[] = "Subido '{$nombreLimpio}', pero falló el insert en BD.";
                    // (no hacemos rollback del archivo)
                } else {
                    $ok++;
                }
            }

            // Mensaje final
            if ($ok > 0 && $fail === 0 && $dbFail === 0) {
                $success = "Archivos subidos y registrados exitosamente ($ok).";
                $rutaJS  = $rutaSQL;
            } else {
                // Prioriza mostrar detalles si hubo fallos
                $texto = [];
                if ($ok)     $texto[] = "$ok subido(s) correctamente";
                if ($fail)   $texto[] = "$fail fallo(s) al mover";
                if ($dbFail) $texto[] = "$dbFail fallo(s) al registrar en BD";
                $error  = implode(' · ', $texto);
                if (!empty($mensajes)) $error .= " — " . implode(' | ', $mensajes);
                if ($ok) { $rutaJS = $rutaSQL; } // si al menos uno OK, devuelve la ruta
            }
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir archivo</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f1f1f1; margin: 0; padding: 30px; }
        .card { background: white; max-width: 500px; margin: auto; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .mensaje { margin: 20px auto; max-width: 500px; padding: 15px; border-radius: 8px; text-align: center; font-weight: 500; }
        .success { background-color: #e6f9ec; color: #2e7d32; border: 1px solid #b2dfdb; }
        .error   { background-color: #fdecea; color: #c62828; border: 1px solid #f5c6cb; }
        .file-upload { position: relative; background: #f9f9f9; padding: 10px; margin-bottom: 10px; border-radius: 8px; box-shadow: 0 0 5px rgba(0,0,0,0.1); text-align: center; }
        .file-upload label { display: inline-block; background-color: #007BFF; color: white; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: 500; transition: background 0.3s ease; }
        .file-upload label:hover { background-color: #0056b3; }
        .file-upload input[type="file"] { display: none; }
        .file-name { margin-top: 5px; font-size: 0.9em; color: #555; }
        .btn-remove { background-color: #dc3545; color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 14px; margin-top: 8px; }
        .btn-remove:hover { background-color: #c82333; }
        .btn-agregar { background:rgb(9, 133, 190); color: white; padding: 10px 25px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; display: block; margin: 10px auto; }
        .btn-subir { background:rgb(9, 156, 4); color: white; padding: 10px 25px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; display: block; margin: 10px auto; }
        .btn-agregar:hover{ background-color:rgb(0, 60, 255); }
        .btn-subir:hover { background-color:rgb(3, 187, 18); }
    </style>
</head>
<body>
<?php if (!empty($error)): ?>
    <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
<?php elseif (!empty($success)): ?>
    <div class="mensaje success"><?= htmlspecialchars($success) ?></div>
    <script>
        if (window.opener && !window.opener.closed) {
            const campo = window.opener.document.getElementById('url');
            if (campo) campo.value = "<?= $rutaJS ?>";
        }
        setTimeout(() => window.close(), 2500);
    </script>
<?php endif; ?>

<div class="card">
    <h2>Subir archivos</h2>
    <p style="text-align:center;">📁 <strong><?= htmlspecialchars($departamento ?? '') ?> / <?= htmlspecialchars($identificacion ?? '') ?></strong></p>

    <form method="post" enctype="multipart/form-data" id="formArchivos">
        <div id="fileInputs">
            <div class="file-upload">
                <label>
                    📎 Seleccionar archivo
                    <input type="file" name="archivo[]" required onchange="mostrarNombre(this)">
                </label>
                <div class="file-name">Ningún archivo seleccionado</div>
                <button type="button" class="btn-remove" onclick="eliminarCampo(this)">❌ Eliminar</button>
            </div>
        </div>
        <button type="button" class="btn-agregar" onclick="agregarCampo()">➕ Agregar archivo</button>
        <button type="submit" class="btn-subir">📤 Subir archivos</button>
    </form>
</div>

<script>
function mostrarNombre(input) {
    const nombre = input.files.length > 0 ? input.files[0].name : "Ningún archivo seleccionado";
    input.closest('.file-upload').querySelector('.file-name').textContent = nombre;
}
function agregarCampo() {
    const contenedor = document.getElementById('fileInputs');
    const nuevo = document.createElement('div');
    nuevo.className = 'file-upload';
    nuevo.innerHTML = `
        <label>
            📎 Seleccionar archivo
            <input type="file" name="archivo[]" onchange="mostrarNombre(this)">
        </label>
        <div class="file-name">Ningún archivo seleccionado</div>
        <button type="button" class="btn-remove" onclick="eliminarCampo(this)">❌ Eliminar</button>
    `;
    contenedor.appendChild(nuevo);
}
function eliminarCampo(boton) {
    const div = boton.closest('.file-upload');
    div.remove();
}
</script>
</body>
</html>
