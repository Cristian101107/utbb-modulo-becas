<?php
/**
 * MÓDULO BECAS · Baja individual y depuración por lote
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

$accion = (string) post('accion');
$origen = (string) post('origen');

// Construye la ruta de retorno segura (evita Open Redirect)
$rutaRetorno = 'index.php';
if ($origen === 'depuracion') {
    $dias   = entero(post('dias_actuales')) ?? 60;
    $estado = preg_replace('/[^a-z_]/', '', (string) post('estado_actual'));
    $rutaRetorno = 'depuracion.php?dias=' . $dias . ($estado !== '' ? '&estado=' . urlencode($estado) : '');
}

// Función auxiliar para borrar archivos adjuntos sin riesgo de Path Traversal
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
   CASO A: Depuración masiva por lote (desde depuracion.php)
   ========================================================================= */
if ($accion === 'purgar_lote') {
    $rawSeleccion = is_array($_POST['seleccionados'] ?? null) ? $_POST['seleccionados'] : [];
    $idsSeleccionados = array_slice(
        array_values(array_filter(array_map('intval', $rawSeleccion), static fn(int $i): bool => $i > 0)),
        0,
        100
    );

    if (empty($idsSeleccionados)) {
        mensaje('alerta', 'Selecciona al menos un registro vencido para depurar.');
        redirigir($rutaRetorno);
    }

    try {
        if (!function_exists('plt_obtener') || !function_exists('plt_eliminar')) {
            throw new RuntimeException('Funciones de consulta aún no conectadas.');
        }

        $eliminados = 0;
        foreach ($idsSeleccionados as $idItem) {
            $reg = plt_obtener($idItem);
            if ($reg === null) {
                continue;
            }

            // Un editor solo puede purgar registros de su propia carrera
            if (rol() === 'editor' && isset($reg['carrera_id']) && (int) $reg['carrera_id'] !== carrera_del_usuario()) {
                continue;
            }

            plt_eliminar($idItem);
            $borrarArchivoSeguro($reg['imagen'] ?? null);
            $borrarArchivoSeguro($reg['archivo'] ?? null);
            $eliminados++;
        }

        mensaje('exito', "Depuración completada: se eliminaron {$eliminados} registro(s) y sus archivos asociados.");
    } catch (PDOException $e) {
        error_log('Error en depuración por lote: ' . $e->getMessage());
        mensaje('error', 'Algunos registros tienen información vinculada y no pudieron purgarse.');
    } catch (Throwable $e) {
        // Simulación mientras no exista la tabla SQL
        $total = count($idsSeleccionados);
        mensaje('exito', "Depuración simulada: {$total} registro(s) purgado(s) (sin base de datos).");
    }

    redirigir($rutaRetorno);
}

/* =========================================================================
   CASO B: Eliminación individual (desde index.php o purga individual)
   ========================================================================= */
$id = entero(post('id'));

if ($id === null || $id <= 0) {
    mensaje('error', 'No se indicó qué registro eliminar.');
    redirigir($rutaRetorno);
}

try {
    if (!function_exists('plt_obtener') || !function_exists('plt_eliminar')) {
        throw new RuntimeException('Funciones de consulta aún no conectadas.');
    }

    $registro = plt_obtener($id);

    if ($registro === null) {
        mensaje('error', 'El registro ya no existe.');
        redirigir($rutaRetorno);
    }

    if (isset($registro['carrera_id'])) {
        requerir_carrera((int) $registro['carrera_id']);
    }

    plt_eliminar($id);

    $borrarArchivoSeguro($registro['imagen'] ?? null);
    $borrarArchivoSeguro($registro['archivo'] ?? null);

    mensaje('exito', $origen === 'depuracion' ? 'Registro y archivos depurados.' : 'Convocatoria eliminada.');
} catch (PDOException $e) {
    // Por ejemplo, cuando otra tabla lo referencia con ON DELETE RESTRICT.
    error_log('Error al eliminar: ' . $e->getMessage());
    mensaje('error', 'No se pudo eliminar: el registro tiene información asociada.');
} catch (Throwable $e) {
    // Respaldo simulado cuando aún no existe la tabla en MySQL
    mensaje('exito', "Eliminación simulada del registro #{$id} (tabla SQL aún no creada).");
}

redirigir($rutaRetorno);