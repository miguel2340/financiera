<?php
// archivo: common_fs.php
define('SOPORTES_BASE', 'E:\\FINANCIERA'); 
// o red: define('SOPORTES_BASE', '\\\\SERVER\\soportes$');

// Normaliza una ruta de entrada (relativa o absoluta) a una ruta absoluta segura dentro de SOPORTES_BASE
function safe_join_under_base(string $raw): ?string {
    $base = rtrim(realpath(SOPORTES_BASE), DIRECTORY_SEPARATOR);
    if (!$base) return null;

    $candidate = trim($raw);
    // Si viene con backslashes, pásalo a DIRECTORY_SEPARATOR
    $candidate = str_replace(['..'], '', $candidate);
    $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
    $candidate = ltrim($candidate, DIRECTORY_SEPARATOR);

    // Si el usuario guardó absoluto, intenta recortar el prefijo base:
    $abs = realpath($raw);
    if ($abs && str_starts_with($abs, $base)) {
        $final = $abs;
    } else {
        // Une a la base
        $final = realpath($base . DIRECTORY_SEPARATOR . $candidate);
    }

    if (!$final) return null;
    // Evita path traversal saliéndose de la base
    if (!str_starts_with($final, $base)) return null;

    return $final;
}
