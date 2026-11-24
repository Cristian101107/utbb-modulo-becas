<?php
/**
 * MÓDULO BECAS · Baja individual y eliminación por lote
 * Explorador Vocacional UTBB
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

if (file_exists(__DIR__ . '/../includes/consultas.php')) {
    require_once __DIR__ . '/../includes/consultas.php';
}

const CARPETA_SUBIDAS = __DIR__ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

verificar_token();

$fnObtener  = function_exists('bec_obtener') ? 'bec_obtener' : (function_exists('plt_obtener') ? 'plt_obtener' : null);
$fnEliminar = function_exists('bec_eliminar') ? 'bec_eliminar' : (function_exists('plt_eliminar') ? 'plt_eliminar' : null);

$accion = (string) post('accion');
$origen = (string) post('origen');

$rutaRetorno = 'index.php';
if ($origen === 'depuracion') {
    $dias        = entero(post('dias_actuales')) ?? 60;
    $estado      = preg_replace('/[^a-z_]/', '', (string) post('estado_actual'));
    $rutaRetorno = 'depuracion.php?dias=' . $dias . ($estado !== '' ? '&estado=' . urlencode($estado) : '');
}

$borrarArchivoSeguro = static function (?string $nombreArchivo): void {
    if (empty($nombreArchivo)) {
        return;
    }
    $archivoLimpio = basename($nombreArchivo);
    $rutaCompleta  = CARPETA_SUBIDAS . '/' . $archivoLimpio;

    if (is_file($rutaCompleta)) {
        @unlink($rutaCompleta);
    }
};

/* =========================================================================
   CASO A: Eliminación masiva por lote
   ========================================================================= */
if ($accion === 'eliminar_lote') {
    $rawSeleccion = is_array($_POST['seleccionados'] ?? null) ? $_POST['seleccionados'] : [];
    $idsSeleccionados = array_slice(
        array_values(array_unique(array_filter(array_map('intval', $rawSeleccion), static fn(int $i): bool => $i > 0))),
        0,
        100
    );

    if (empty($idsSeleccionados)) {
        mensaje('alerta', 'Selecciona al menos un registro vencido para eliminar.');
        redirigir($rutaRetorno);
    }

    try {
        if ($fnObtener === null || $fnEliminar === null) {
            throw new RuntimeException('Funciones bec_obtener / bec_eliminar no disponibles en consultas.php.');
        }

        $eliminados = 0;
        foreach ($idsSeleccionados as $idItem) {
            $reg = $fnObtener($idItem);
            if ($reg === null) {
                continue;
            }

            if (rol() === 'editor' && (!isset($reg['carrera_id']) || (int) $reg['carrera_id'] !== carrera_del_usuario())) {
                continue;
            }

            $fnEliminar($idItem);
            $borrarArchivoSeguro($reg['imagen'] ?? null);
            $borrarArchivoSeguro($reg['archivo'] ?? null);
            $eliminados++;
        }

        mensaje('exito', "Eliminación completada: se eliminaron {$eliminados} registro(s) y sus archivos asociados.");
    } catch (PDOException $e) {
        error_log('Error en eliminación por lote: ' . $e->getMessage());
        mensaje('error', 'Algunos registros tienen información vinculada y no pudieron eliminarse.');
    } catch (Throwable $e) {
        error_log('Error inesperado en eliminación por lote: ' . $e->getMessage());
        mensaje('error', 'No se pudo completar la eliminación por un error de configuración o de base de datos.');
    }

    redirigir($rutaRetorno);
}

/* =========================================================================
   CASO B: Eliminación individual
   ========================================================================= */
$id = entero(post('id'));

if ($id === null || $id <= 0) {
    mensaje('error', 'No se indicó qué registro eliminar.');
    redirigir($rutaRetorno);
}

try {
    if ($fnObtener === null || $fnEliminar === null) {
        throw new RuntimeException('Funciones bec_obtener / bec_eliminar no disponibles en consultas.php.');
    }

    $registro = $fnObtener($id);

    if ($registro === null) {
        mensaje('error', 'El registro ya no existe.');
        redirigir($rutaRetorno);
    }

    if (isset($registro['carrera_id']) && $registro['carrera_id'] !== null) {
        requerir_carrera((int) $registro['carrera_id']);
    }

    $fnEliminar($id);

    $borrarArchivoSeguro($registro['imagen'] ?? null);
    $borrarArchivoSeguro($registro['archivo'] ?? null);

    mensaje('exito', $origen === 'depuracion' ? 'Registro y archivos eliminados.' : 'Convocatoria eliminada.');
} catch (PDOException $e) {
    error_log('Error al eliminar: ' . $e->getMessage());
    mensaje('error', 'No se pudo eliminar: el registro tiene información asociada.');
} catch (Throwable $e) {
    error_log('Error inesperado al eliminar el registro #' . $id . ': ' . $e->getMessage());
    mensaje('error', 'No se pudo eliminar el registro por un error de configuración o de base de datos.');
}

redirigir($rutaRetorno);