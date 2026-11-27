<?php
require_once __DIR__ . '/../../../nucleo/comun.php';
requerir_rol('editor', 'coordinacion');
require_once __DIR__ . '/../includes/consultas.php';

$id  = entero(get('id')) ?? 0;
$doc = bec_obtener_documento($id);

if (!$doc) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

if (rol() === 'editor' && (int) $doc['carrera_id'] !== carrera_del_usuario()) {
    http_response_code(403);
    exit('Sin permiso.');
}

$archivo = basename($doc['nombre_guardado']);
$ruta    = __DIR__ . '/../uploads/' . $archivo;

if (!is_file($ruta)) {
    $ruta = __DIR__ . '/../uploads/solicitudes/' . $archivo;
}

if (!is_file($ruta)) {
    http_response_code(404);
    exit('Archivo no encontrado en el servidor.');
}

header('Content-Type: ' . $doc['tipo_mime']);
header('Content-Disposition: inline; filename="' . $archivo . '"');
readfile($ruta);
exit;