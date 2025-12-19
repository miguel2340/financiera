<?php
$ruta = $_GET['ruta'] ?? '';

if (!is_dir($ruta)) {
    echo "<p><strong>Ruta inválida o no encontrada.</strong></p>";
    exit;
}

$carpeta_web = str_replace('E:/FINANCIERA', '/financiera', $ruta);
$carpeta_web = str_replace('\\', '/', $carpeta_web);

$archivos = array_diff(scandir($ruta), ['.', '..']);

if (empty($archivos)) {
    echo "<p>No hay archivos en esta carpeta.</p>";
} else {
    echo "<style>
        .lista-archivos {
            list-style: none;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
        }
        .archivo-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .archivo-item:hover {
            background-color: #f5f5f5;
        }
        .archivo-icono {
            font-size: 20px;
        }
        .archivo-nombre {
            font-size: 15px;
            color: #333;
        }
    </style>";

    echo "<ul class='lista-archivos'>";

    foreach ($archivos as $archivo) {
        $url = $carpeta_web . '/' . rawurlencode($archivo);
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

        $icono = '📄'; // default
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) $icono = '🖼️';
        elseif ($extension === 'pdf') $icono = '📄';
        elseif (in_array($extension, ['doc', 'docx'])) $icono = '📝';
        elseif (in_array($extension, ['xls', 'xlsx', 'csv'])) $icono = '📊';
        elseif (in_array($extension, ['zip', 'rar'])) $icono = '🗜';

        echo "<li class='archivo-item' onclick=\"abrirPopup('$url')\">
                <span class='archivo-icono'>$icono</span>
                <span class='archivo-nombre'>" . htmlspecialchars($archivo) . "</span>
              </li>";
    }

    echo "</ul>";
}
?>
