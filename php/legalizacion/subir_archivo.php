<?php
session_start();
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

// Verificar si no hay sesión activa y redirigir al login
if (!isset($_SESSION['usuario_autenticado']) || !isset($_SESSION['nombre_usuario'])) {
    header("Location: ../../inicio_sesion.php");
    exit;
}

$usuario = $_SESSION['nombre_usuario'];
$departamento = $_GET['departamento'] ?? '';
$identificacion = $_GET['identificacion'] ?? '';

if (empty($departamento) || empty($identificacion)) {
    $error = "Faltan datos en la URL.";
} else {
    $basePath = "E:\\FINANCIERA\\$departamento\\$identificacion";

    // Crear carpeta base si no existe
    if (!is_dir($basePath)) {
        mkdir($basePath, 0777, true);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
        // Crear subcarpeta con fecha y hora
        $fechaCarpeta = date("Ymd_His");
        $pathFinal = $basePath . DIRECTORY_SEPARATOR . $fechaCarpeta;
        if (!is_dir($pathFinal)) {
            mkdir($pathFinal, 0777, true);
        }

        foreach ($_FILES['archivo']['name'] as $index => $fileName) {
            $tmpName = $_FILES['archivo']['tmp_name'][$index];
            $nombre = basename($fileName);
            $destino = $pathFinal . DIRECTORY_SEPARATOR . $nombre;

            if (move_uploaded_file($tmpName, $destino)) {
                $rutaSQL = str_replace("\\", "/", $pathFinal);
                $fecha = date("Y-m-d H:i:s");

                $sql = "INSERT INTO archivos_subidos (usuario, identificacion, fecha_subida, ruta_archivo)
                        VALUES (?, ?, ?, ?)";
                $params = [$usuario, $identificacion, $fecha, $rutaSQL];
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    $error = "Uno o más archivos se subieron, pero ocurrió un error al registrar en la base de datos.";
                } else {
                    $success = "Archivos subidos y registrados exitosamente.";
                    $rutaJS = $rutaSQL;
                }
            } else {
                $error = "Error al mover uno de los archivos al destino.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir archivo</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f1f1f1;
            margin: 0;
            padding: 30px;
        }
        .card {
            background: white;
            max-width: 500px;
            margin: auto;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .mensaje {
            margin: 20px auto;
            max-width: 500px;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }
        .success {
            background-color: #e6f9ec;
            color: #2e7d32;
            border: 1px solid #b2dfdb;
        }
        .error {
            background-color: #fdecea;
            color: #c62828;
            border: 1px solid #f5c6cb;
        }

        .file-upload {
            position: relative;
            background: #f9f9f9;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            text-align: center;
        }

        .file-upload label {
            display: inline-block;
            background-color: #007BFF;
            color: white;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .file-upload label:hover {
            background-color: #0056b3;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-name {
            margin-top: 5px;
            font-size: 0.9em;
            color: #555;
        }

        .btn-remove {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 8px;
        }

        .btn-remove:hover {
            background-color: #c82333;
        }
        .btn-agregar {
            background:rgb(9, 133, 190);
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            display: block;
            margin: 10px auto;
        }

        .btn-subir {
            background:rgb(9, 156, 4);
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            display: block;
            margin: 10px auto;
        }

        .btn-agregar:hover{
            background-color:rgb(0, 60, 255);
        }
        
       .btn-subir:hover {
            background-color:rgb(3, 187, 18);
        }
    </style>
</head>
<body>
<?php if (!empty($error)): ?>
    <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
<?php elseif (!empty($success)): ?>
    <div class="mensaje success"><?= htmlspecialchars($success) ?></div>
    <script>
        // Asignar valor al campo 'url' en la ventana anterior
        if (window.opener && !window.opener.closed) {
            const campo = window.opener.document.getElementById('url');
            if (campo) campo.value = "<?= $rutaJS ?>";
        }

        // Cerrar la ventana luego de 2.5 segundos
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
            <input type="file" name="archivo[]" required onchange="mostrarNombre(this)">
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
