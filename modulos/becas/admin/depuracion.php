<?php
/**
 * MÓDULO BECAS · Eliminación de convocatorias y expedientes vencidos
 * Explorador Vocacional UTBB
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

// 1. Verificación de rol obligatoria
requerir_rol('editor', 'coordinacion');

if (file_exists(__DIR__ . '/../includes/consultas.php')) {
    require_once __DIR__ . '/../includes/consultas.php';
}

$diasPermitidos    = [30, 60, 90, 180];
$estadosPermitidos = ['cerrada', 'aceptada_vencida', 'rechazada'];

/** Obtiene la conexión PDO oficial del núcleo mediante bd(). */
function conexion_becas_depuracion(): ?\PDO {
    try {
        if (function_exists('bd')) {
            $pdo = bd();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }
    } catch (\Throwable $e) {
        error_log('Error al conectar con bd() en depuracion.php: ' . $e->getMessage());
    }

    foreach (['pdo', 'conexion', 'db', 'conn'] as $nombre) {
        if (isset($GLOBALS[$nombre]) && $GLOBALS[$nombre] instanceof \PDO) {
            return $GLOBALS[$nombre];
        }
    }

    return null;
}

function identificador_sql_depuracion(string $identificador): ?string {
    $identificador = trim($identificador);
    return preg_match('/^[A-Za-z0-9_$]+$/', $identificador) === 1
        ? $identificador
        : null;
}

function tablas_convocatorias_depuracion(\PDO $conexion): array {
    $candidatas = ['bec_programas'];

    try {
        $filas = $conexion->query('SHOW TABLES')?->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        foreach ($filas as $tabla) {
            $nombre = strtolower((string) $tabla);
            if ((str_contains($nombre, 'convocatoria') || str_starts_with($nombre, 'bec_')) && !in_array($tabla, $candidatas, true)) {
                $candidatas[] = (string) $tabla;
            }
        }
    } catch (\Throwable) {
    }

    return $candidatas;
}

function cargar_convocatorias_depuracion(): array {
    $conexion = conexion_becas_depuracion();
    if ($conexion === null) {
        return ['tabla' => null, 'id' => null, 'tiene_carrera' => false, 'registros' => []];
    }

    foreach (tablas_convocatorias_depuracion($conexion) as $tabla) {
        $tablaSegura = identificador_sql_depuracion((string) $tabla);
        if ($tablaSegura === null) {
            continue;
        }

        try {
            $sentencia = $conexion->query('SHOW COLUMNS FROM `' . $tablaSegura . '`');
            $columnas  = $sentencia ? $sentencia->fetchAll(\PDO::FETCH_COLUMN) : [];

            $normalizadas = [];
            foreach ($columnas as $columna) {
                $normalizadas[strtolower((string) $columna)] = (string) $columna;
            }

            $columnaId          = $normalizadas['id'] ?? $normalizadas['id_convocatoria'] ?? null;
            $columnaTitulo      = $normalizadas['titulo'] ?? $normalizadas['nombre'] ?? $normalizadas['titulo_convocatoria'] ?? null;
            $columnaVencimiento = $normalizadas['cierre'] ?? $normalizadas['fecha_cierre'] ?? $normalizadas['fecha_vencimiento'] ?? $normalizadas['fecha_fin'] ?? $normalizadas['fecha_limite'] ?? null;
            $columnaEstado      = $normalizadas['estado'] ?? $normalizadas['estatus'] ?? null;
            $columnaCarrera     = $normalizadas['carrera_id'] ?? null;

            if ($columnaId === null || $columnaTitulo === null || $columnaVencimiento === null) {
                continue;
            }

            $colIdSeg     = identificador_sql_depuracion($columnaId);
            $colTituloSeg = identificador_sql_depuracion($columnaTitulo);
            $colVencSeg   = identificador_sql_depuracion($columnaVencimiento);
            $colEstSeg    = $columnaEstado !== null ? identificador_sql_depuracion($columnaEstado) : null;
            $colCarrSeg   = $columnaCarrera !== null ? identificador_sql_depuracion($columnaCarrera) : null;

            if ($colIdSeg === null || $colTituloSeg === null || $colVencSeg === null) {
                continue;
            }

            $campos = ['`' . $colIdSeg . '`', '`' . $colTituloSeg . '`', '`' . $colVencSeg . '`'];
            if ($colEstSeg !== null) {
                $campos[] = '`' . $colEstSeg . '`';
            }
            if ($colCarrSeg !== null) {
                $campos[] = '`' . $colCarrSeg . '`';
            }

            $sql   = 'SELECT ' . implode(', ', $campos) . ' FROM `' . $tablaSegura . '`';
            $filas = $conexion->query($sql)?->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $carreraUsuario = (rol() === 'editor') ? carrera_del_usuario() : null;
            $registros      = [];

            foreach ($filas as $fila) {
                $id = (int) ($fila[$columnaId] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                // Restricción por carrera para rol editor
                if ($carreraUsuario !== null && $columnaCarrera !== null) {
                    if ((int) ($fila[$columnaCarrera] ?? 0) !== (int) $carreraUsuario) {
                        continue;
                    }
                }

                $fecha = trim((string) ($fila[$columnaVencimiento] ?? ''));
                if ($fecha === '') {
                    continue;
                }

                $estadoTexto = $columnaEstado !== null ? trim((string) ($fila[$columnaEstado] ?? '')) : '';
                $estadoRaw   = $columnaEstado !== null ? mb_strtolower($estadoTexto, 'UTF-8') : 'cerrada';

                if (in_array($estadoRaw, ['cerrada', 'cerrado', 'finalizada', 'finalizado', 'terminada', 'inactiva'], true)) {
                    $estado = 'cerrada';
                } elseif (in_array($estadoRaw, ['rechazada', 'rechazado', 'denegada'], true)) {
                    $estado = 'rechazada';
                } elseif (in_array($estadoRaw, ['aceptada', 'aceptado', 'vigencia_concluida', 'vigencia_vencida'], true)) {
                    $estado = 'aceptada_vencida';
                } else {
                    $estado = 'cerrada';
                }

                $registros[] = [
                    'id'                => $id,
                    'carrera_id'        => $columnaCarrera !== null ? (int) ($fila[$columnaCarrera] ?? 0) : null,
                    'categoria'         => 'Convocatoria',
                    'titulo'            => (string) ($fila[$columnaTitulo] ?? 'Convocatoria'),
                    'detalle'           => 'Convocatoria registrada en BD',
                    'fecha_vencimiento' => substr($fecha, 0, 10),
                    'estado'            => $estado,
                ];
            }

            return [
                'tabla'         => $tablaSegura,
                'id'            => $colIdSeg,
                'tiene_carrera' => $colCarrSeg !== null,
                'registros'     => $registros,
            ];
        } catch (\Throwable) {
        }
    }

    return ['tabla' => null, 'id' => null, 'tiene_carrera' => false, 'registros' => []];
}

function eliminar_convocatoria_depuracion(?string $tabla, ?string $columnaId, int $id): bool {
    if ($tabla === null || $columnaId === null || $id <= 0) {
        return false;
    }

    // Si existe la función oficial del módulo, la usamos primero para respetar cascadas o archivos
    if (function_exists('bec_eliminar')) {
        try {
            bec_eliminar($id);
            return true;
        } catch (\Throwable) {
        }
    }

    $conexion = conexion_becas_depuracion();
    if ($conexion === null) {
        return false;
    }

    $tablaSegura   = identificador_sql_depuracion($tabla);
    $columnaSegura = identificador_sql_depuracion($columnaId);
    if ($tablaSegura === null || $columnaSegura === null) {
        return false;
    }

    try {
        $sentencia = $conexion->prepare(
            'DELETE FROM `' . $tablaSegura . '` WHERE `' . $columnaSegura . '` = :id LIMIT 1'
        );
        $sentencia->execute(['id' => $id]);
        return $sentencia->rowCount() === 1;
    } catch (\Throwable $e) {
        error_log('Error al depurar convocatoria #' . $id . ': ' . $e->getMessage());
        return false;
    }
}

$datosConvocatorias     = cargar_convocatorias_depuracion();
$tablaConvocatorias     = $datosConvocatorias['tabla'];
$columnaIdConvocatorias = $datosConvocatorias['id'];
$registrosHistoricos    = $datosConvocatorias['registros'];

$diasRaw       = entero(get('dias')) ?? 60;
$diasRetencion = in_array($diasRaw, $diasPermitidos, true) ? $diasRaw : 60;

$estadoRaw    = trim((string) get('estado'));
$filtroEstado = in_array($estadoRaw, $estadosPermitidos, true) ? $estadoRaw : '';

$alerta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();

    $accion = (string) ($_POST['accion'] ?? '');

    $diasPost      = entero($_POST['dias_actuales'] ?? null) ?? 60;
    $diasRetencion = in_array($diasPost, $diasPermitidos, true) ? $diasPost : 60;

    $estadoPost   = trim((string) ($_POST['estado_actual'] ?? ''));
    $filtroEstado = in_array($estadoPost, $estadosPermitidos, true) ? $estadoPost : '';

    if ($accion === 'eliminar_uno') {
        $idEliminar      = entero($_POST['id'] ?? null) ?? 0;
        $tituloEliminado = '';

        foreach ($registrosHistoricos as $idx => $item) {
            if ($item['id'] !== $idEliminar) {
                continue;
            }

            try {
                $fechaVencimiento  = new DateTime($item['fecha_vencimiento']);
                $hoyValidacion     = new DateTime('today');
                $diasTranscurridos = ($fechaVencimiento < $hoyValidacion)
                    ? (int) $hoyValidacion->diff($fechaVencimiento)->days
                    : 0;
            } catch (\Throwable) {
                continue;
            }

            if ($diasTranscurridos < $diasRetencion) {
                break;
            }
            if ($filtroEstado !== '' && $item['estado'] !== $filtroEstado) {
                break;
            }

            if (eliminar_convocatoria_depuracion($tablaConvocatorias, $columnaIdConvocatorias, $idEliminar)) {
                $tituloEliminado = $item['titulo'];
                unset($registrosHistoricos[$idx]);
            }
            break;
        }

        if ($tituloEliminado !== '') {
            $alerta = [
                'tipo'   => 'exito',
                'titulo' => 'Convocatoria eliminada',
                'texto'  => "Se eliminó «{$tituloEliminado}» de la base de datos.",
            ];
        } else {
            $alerta = [
                'tipo'   => 'error',
                'titulo' => 'No fue posible eliminar',
                'texto'  => 'El registro no existe, no es elegible o tiene información vinculada en la base de datos.',
            ];
        }
    } elseif ($accion === 'eliminar_lote') {
        $rawSeleccion = is_array($_POST['seleccionados'] ?? null) ? $_POST['seleccionados'] : [];
        $idsSeleccionados = array_slice(
            array_values(array_unique(array_filter(array_map('intval', $rawSeleccion), static fn(int $id): bool => $id > 0))),
            0,
            100
        );

        if (empty($idsSeleccionados)) {
            $alerta = [
                'tipo'   => 'alerta',
                'titulo' => 'Sin selección válida',
                'texto'  => 'Selecciona al menos un registro vencido en la tabla para ejecutar la eliminación masiva.',
            ];
        } else {
            $eliminados = 0;
            $hoyLote    = new DateTime('today');

            foreach ($registrosHistoricos as $idx => $item) {
                if (!in_array($item['id'], $idsSeleccionados, true)) {
                    continue;
                }

                try {
                    $fechaVencimiento  = new DateTime($item['fecha_vencimiento']);
                    $diasTranscurridos = ($fechaVencimiento < $hoyLote)
                        ? (int) $hoyLote->diff($fechaVencimiento)->days
                        : 0;
                } catch (\Throwable) {
                    continue;
                }

                if ($diasTranscurridos < $diasRetencion) {
                    continue;
                }
                if ($filtroEstado !== '' && $item['estado'] !== $filtroEstado) {
                    continue;
                }

                if (eliminar_convocatoria_depuracion($tablaConvocatorias, $columnaIdConvocatorias, $item['id'])) {
                    unset($registrosHistoricos[$idx]);
                    $eliminados++;
                }
            }

            $alerta = [
                'tipo'   => $eliminados > 0 ? 'exito' : 'error',
                'titulo' => 'Eliminación por lote completada',
                'texto'  => $eliminados > 0
                    ? "Se eliminaron {$eliminados} convocatoria(s) vencida(s) de la base de datos."
                    : 'Ningún registro fue eliminado. Puede tener solicitudes vinculadas o no ser elegible.',
            ];
        }
    }
}

$hoy        = new DateTime('today');
$candidatos = [];

foreach ($registrosHistoricos as $item) {
    $fechaVenc         = new DateTime($item['fecha_vencimiento']);
    $diasTranscurridos = ($fechaVenc < $hoy) ? (int) $hoy->diff($fechaVenc)->days : 0;

    if ($diasTranscurridos < $diasRetencion) {
        continue;
    }
    if ($filtroEstado !== '' && $item['estado'] !== $filtroEstado) {
        continue;
    }

    $item['dias_transcurridos'] = $diasTranscurridos;
    $candidatos[]               = $item;
}

cabecera('Eliminación · Becas y apoyos', '', '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li><a href="index.php">Becas y apoyos</a></li>
      <li>Eliminación</li>
    </ol>
  </nav>

  <header class="fila fila--separada mb-6">
    <h1 class="sin-margen">Eliminación de convocatorias vencidas</h1>
    <a class="boton boton--secundario" href="index.php">Volver al listado</a>
  </header>

  <?php if ($alerta !== null): ?>
    <aside class="alerta alerta--<?= e($alerta['tipo']) ?> mb-6" role="status">
      <strong><?= e($alerta['titulo']) ?>:</strong> <?= e($alerta['texto']) ?>
    </aside>
  <?php endif; ?>

  <form class="panel mb-6" method="GET" action="depuracion.php">
    <fieldset class="fila becas-fieldset">
      <p class="campo sin-margen becas-campo-filtro">
        <label class="campo__etiqueta" for="dias">Antigüedad tras vencimiento</label>
        <select class="campo__control" id="dias" name="dias">
          <option value="30" <?= $diasRetencion === 30 ? 'selected' : '' ?>>Más de 30 días vencidos</option>
          <option value="60" <?= $diasRetencion === 60 ? 'selected' : '' ?>>Más de 60 días vencidos</option>
          <option value="90" <?= $diasRetencion === 90 ? 'selected' : '' ?>>Más de 90 días vencidos</option>
          <option value="180" <?= $diasRetencion === 180 ? 'selected' : '' ?>>Más de 6 meses vencidos</option>
        </select>
      </p>

      <p class="campo sin-margen becas-campo-busqueda">
        <label class="campo__etiqueta" for="estado">Motivo de eliminación</label>
        <select class="campo__control" id="estado" name="estado">
          <option value="">Todos los vencidos o resueltos</option>
          <option value="cerrada" <?= $filtroEstado === 'cerrada' ? 'selected' : '' ?>>Convocatorias cerradas</option>
          <option value="aceptada_vencida" <?= $filtroEstado === 'aceptada_vencida' ? 'selected' : '' ?>>Aceptadas (vigencia concluida)</option>
          <option value="rechazada" <?= $filtroEstado === 'rechazada' ? 'selected' : '' ?>>Solicitudes rechazadas</option>
        </select>
      </p>

      <button class="boton boton--secundario" type="submit">Aplicar criterio</button>
    </fieldset>
  </form>

  <?php if (empty($candidatos)): ?>

    <article class="vacio">
      <h2>Sin registros por eliminar</h2>
      <p class="ancho-lectura">No hay convocatorias ni expedientes que superen los <?= (int) $diasRetencion ?> días de antigüedad con este criterio.</p>
    </article>

  <?php else: ?>

    <form method="POST" action="depuracion.php" id="form-eliminar-lote"
          onsubmit="return confirm('¿Eliminar los registros y archivos seleccionados?')">
      <?= campo_token() ?>
      <input type="hidden" name="accion" value="eliminar_lote">
      <input type="hidden" name="dias_actuales" value="<?= (int) $diasRetencion ?>">
      <input type="hidden" name="estado_actual" value="<?= e($filtroEstado) ?>">

      <header class="fila fila--separada mb-6">
        <p class="sin-margen">
          <strong><?= count($candidatos) ?></strong> convocatoria(s) con &ge; <?= (int) $diasRetencion ?> días vencidas.
        </p>
        <button class="boton boton--peligro boton--pequeno" type="submit">Eliminar seleccionados</button>
      </header>

      <figure class="tabla-envoltura sin-margen">
        <table class="tabla">
          <thead>
            <tr>
              <th scope="col" class="beca-col-check">
                <input type="checkbox" id="seleccionar-todos" aria-label="Seleccionar todos">
              </th>
              <th scope="col">Registro / Expediente</th>
              <th scope="col">Tipo</th>
              <th scope="col">Motivo</th>
              <th scope="col">Venció hace</th>
              <th scope="col">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($candidatos as $item): ?>
              <tr>
                <td class="beca-col-check">
                  <input type="checkbox" name="seleccionados[]" value="<?= (int) $item['id'] ?>"
                         class="check-eliminacion" aria-label="Seleccionar <?= e($item['titulo']) ?>">
                </td>
                <td>
                  <strong><?= e($item['titulo']) ?></strong><br>
                  <small><?= e($item['detalle']) ?></small>
                </td>
                <td><?= e($item['categoria']) ?></td>
                <td>
                  <?php
                  $etiquetas = [
                      'cerrada'          => 'Convocatoria cerrada',
                      'aceptada_vencida' => 'Aceptada · Vigencia fin',
                      'rechazada'        => 'Rechazada',
                  ];
                  ?>
                  <mark class="beca-estado beca-estado--<?= e($item['estado']) ?>">
                    <?= e($etiquetas[$item['estado']] ?? 'Vencido') ?>
                  </mark>
                </td>
                <td>
                  <strong><?= (int) $item['dias_transcurridos'] ?> días</strong><br>
                  <small>Desde <time datetime="<?= e($item['fecha_vencimiento']) ?>"><?= e(date('d/m/Y', strtotime($item['fecha_vencimiento']))) ?></time></small>
                </td>
                <td>
                  <menu class="tabla__acciones">
                    <li>
                      <button class="boton boton--peligro boton--pequeno" type="submit"
                              form="form-eliminar-<?= (int) $item['id'] ?>">
                        Eliminar
                      </button>
                    </li>
                  </menu>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </figure>
    </form>

    <?php foreach ($candidatos as $item): ?>
      <form method="POST" action="depuracion.php" id="form-eliminar-<?= (int) $item['id'] ?>"
            onsubmit="return confirm('¿Eliminar este registro y sus archivos?')">
        <?= campo_token() ?>
        <input type="hidden" name="accion" value="eliminar_uno">
        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
        <input type="hidden" name="dias_actuales" value="<?= (int) $diasRetencion ?>">
        <input type="hidden" name="estado_actual" value="<?= e($filtroEstado) ?>">
      </form>
    <?php endforeach; ?>

  <?php endif; ?>

</section>

<script>
document.getElementById('seleccionar-todos')?.addEventListener('change', function (e) {
  document.querySelectorAll('.check-eliminacion').forEach(function (chk) {
    chk.checked = e.target.checked;
  });
});
</script>

<?php
pie();