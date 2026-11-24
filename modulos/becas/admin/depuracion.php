<?php
/**
 * MÓDULO BECAS · Eliminación de convocatorias y expedientes vencidos
 * Explorador Vocacional UTBB
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

// 1. Verificación de rol obligatoria
requerir_rol('editor', 'coordinacion');

$diasPermitidos    = [30, 60, 90, 180];
$estadosPermitidos = ['cerrada'];

/** Encuentra la tabla y las columnas reales de convocatorias sin depender de datos de ejemplo. */
function conexion_becas_depuracion() {
    foreach (['pdo', 'conexion', 'db', 'mysqli', 'conn'] as $nombre) {
        if (isset($GLOBALS[$nombre]) && is_object($GLOBALS[$nombre])) {
            return $GLOBALS[$nombre];
        }
    }

    // El módulo compartido puede publicar la conexión con otro nombre.
    foreach ($GLOBALS as $valor) {
        if ($valor instanceof \PDO || $valor instanceof \mysqli) {
            return $valor;
        }
        if (is_object($valor) && method_exists($valor, 'query') && method_exists($valor, 'prepare')) {
            return $valor;
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

function tablas_convocatorias_depuracion($conexion): array {
    $candidatas = [];

    try {
        if ($conexion instanceof \PDO) {
            $filas = $conexion->query("SHOW TABLES")?->fetchAll(\PDO::FETCH_COLUMN);
        } elseif (method_exists($conexion, 'query')) {
            $resultado = $conexion->query('SHOW TABLES');
            $filas = [];
            if ($resultado) {
                while ($fila = $resultado->fetch_row()) {
                    $filas[] = $fila[0];
                }
            }
        } else {
            $filas = [];
        }

        foreach (($filas ?: []) as $tabla) {
            $nombre = strtolower((string) $tabla);
            if (str_contains($nombre, 'convocatoria')) {
                $candidatas[] = (string) $tabla;
            }
        }
    } catch (\Throwable) {
    }

    if (empty($candidatas)) {
        foreach (['convocatorias', 'becas_convocatorias', 'convocatoria', 'becas_convocatoria'] as $tabla) {
            $candidatas[] = $tabla;
        }
    }

    return $candidatas;
}

function cargar_convocatorias_depuracion(): array {
    $conexion = conexion_becas_depuracion();
    if ($conexion === null) {
        return ['tabla' => null, 'id' => null, 'registros' => []];
    }

    foreach (tablas_convocatorias_depuracion($conexion) as $tabla) {
        try {
            if ($conexion instanceof \PDO) {
                $sentencia = $conexion->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $tabla) . '`');
                $sentencia->execute();
                $columnas = $sentencia->fetchAll(\PDO::FETCH_COLUMN);
            } else {
                $resultado = $conexion->query('SHOW COLUMNS FROM `' . str_replace('`', '', $tabla) . '`');
                $columnas = [];
                if ($resultado) {
                    while ($fila = $resultado->fetch_assoc()) {
                        $columnas[] = $fila['Field'];
                    }
                }
            }

            $normalizadas = [];
            foreach ($columnas as $columna) {
                $normalizadas[strtolower((string) $columna)] = (string) $columna;
            }

            $columnaId = $normalizadas['id_convocatoria'] ?? $normalizadas['id'] ?? null;
            $columnaTitulo = $normalizadas['titulo'] ?? $normalizadas['nombre'] ?? $normalizadas['titulo_convocatoria'] ?? $normalizadas['nombre_convocatoria'] ?? null;
            $columnaVencimiento = $normalizadas['fecha_vencimiento'] ?? $normalizadas['fecha_cierre'] ?? $normalizadas['fecha_fin'] ?? $normalizadas['fecha_fin_convocatoria'] ?? $normalizadas['fecha_limite'] ?? $normalizadas['vencimiento'] ?? $normalizadas['cierre'] ?? null;
            $columnaEstado = $normalizadas['estado'] ?? $normalizadas['estatus'] ?? $normalizadas['status'] ?? $normalizadas['situacion'] ?? null;

            if ($columnaId === null || $columnaTitulo === null || $columnaVencimiento === null) {
                continue;
            }

            $tablaSegura = identificador_sql_depuracion((string) $tabla);
            $columnaIdSegura = identificador_sql_depuracion((string) $columnaId);
            $columnaTituloSegura = identificador_sql_depuracion((string) $columnaTitulo);
            $columnaVencimientoSegura = identificador_sql_depuracion((string) $columnaVencimiento);
            $columnaEstadoSegura = $columnaEstado !== null
                ? identificador_sql_depuracion((string) $columnaEstado)
                : null;

            if ($tablaSegura === null || $columnaIdSegura === null || $columnaTituloSegura === null || $columnaVencimientoSegura === null) {
                continue;
            }

            $sql = 'SELECT `' . $columnaIdSegura . '`, `' . $columnaTituloSegura . '`, `' . $columnaVencimientoSegura . '`';
            if ($columnaEstadoSegura !== null) {
                $sql .= ', `' . $columnaEstadoSegura . '`';
            }
            $sql .= ' FROM `' . $tablaSegura . '`';
            if ($conexion instanceof \PDO) {
                $filas = $conexion->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $resultado = $conexion->query($sql);
                $filas = [];
                if ($resultado) {
                    while ($fila = $resultado->fetch_assoc()) {
                        $filas[] = $fila;
                    }
                }
            }

            $registros = [];
            foreach ($filas as $fila) {
                $id = (int) ($fila[$columnaId] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $fecha = trim((string) ($fila[$columnaVencimiento] ?? ''));
                if ($fecha === '') {
                    continue;
                }

                $estadoTexto = $columnaEstado !== null
                    ? trim((string) ($fila[$columnaEstado] ?? ''))
                    : '';
                $estadoRaw = $columnaEstado !== null
                    ? (function_exists('mb_strtolower') ? mb_strtolower($estadoTexto, 'UTF-8') : strtolower($estadoTexto))
                    : 'cerrada';

                // Normaliza estados comunes sin inventar estados que no existan en la base.
                if (in_array($estadoRaw, ['cerrada', 'cerrado', 'finalizada', 'finalizado', 'terminada', 'terminado', 'inactiva', 'inactivo'], true)) {
                    $estado = 'cerrada';
                } elseif (in_array($estadoRaw, ['rechazada', 'rechazado', 'denegada', 'denegado'], true)) {
                    $estado = 'rechazada';
                } elseif (in_array($estadoRaw, ['aceptada', 'aceptado', 'vigencia_concluida', 'vigencia_vencida'], true)) {
                    $estado = 'aceptada_vencida';
                } else {
                    $estado = 'cerrada';
                }

                $registros[] = [
                    'id' => $id,
                    'categoria' => 'Convocatoria',
                    'titulo' => (string) ($fila[$columnaTitulo] ?? 'Convocatoria'),
                    'detalle' => 'Convocatoria registrada',
                    'fecha_vencimiento' => substr($fecha, 0, 10),
                    'estado' => $estado,
                ];
            }

            return ['tabla' => $tablaSegura, 'id' => $columnaId, 'registros' => $registros];
        } catch (\Throwable) {
        }
    }

    return ['tabla' => null, 'id' => null, 'registros' => []];
}

/** Elimina una convocatoria real en la tabla detectada y devuelve si la operación tuvo éxito. */
function eliminar_convocatoria_depuracion(?string $tabla, ?string $columnaId, int $id): bool {
    if ($tabla === null || $columnaId === null || $id <= 0) {
        return false;
    }

    $conexion = conexion_becas_depuracion();
    if ($conexion === null) {
        return false;
    }

    $tablaSegura = identificador_sql_depuracion($tabla);
    $columnaSegura = identificador_sql_depuracion($columnaId);
    if ($tablaSegura === null || $columnaSegura === null) {
        return false;
    }

    try {
        if ($conexion instanceof \PDO) {
            $sentencia = $conexion->prepare(
                'DELETE FROM `' . $tablaSegura . '` WHERE `' . $columnaSegura . '` = :id LIMIT 1'
            );
            $sentencia->execute(['id' => $id]);
            return $sentencia->rowCount() === 1;
        }

        if (method_exists($conexion, 'prepare')) {
            $sentencia = $conexion->prepare(
                'DELETE FROM `' . $tablaSegura . '` WHERE `' . $columnaSegura . '` = ? LIMIT 1'
            );
            if ($sentencia === false) {
                return false;
            }
            $sentencia->bind_param('i', $id);
            $resultado = $sentencia->execute();
            $eliminados = $sentencia->affected_rows;
            $sentencia->close();
            return $resultado && $eliminados === 1;
        }
    } catch (\Throwable) {
        return false;
    }

    return false;
}

$datosConvocatorias = cargar_convocatorias_depuracion();
$tablaConvocatorias = $datosConvocatorias['tabla'];
$columnaIdConvocatorias = $datosConvocatorias['id'];
$registrosHistoricos = $datosConvocatorias['registros'];

$diasRaw = entero(get('dias')) ?? 60;
$diasRetencion = in_array($diasRaw, $diasPermitidos, true) ? $diasRaw : 60;

$estadoRaw = trim((string) get('estado'));
$filtroEstado = in_array($estadoRaw, $estadosPermitidos, true) ? $estadoRaw : '';

$alerta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. Verificación anti-CSRF obligatoria
    if (function_exists('verificar_token')) {
        verificar_token();
    }

    $accion = (string) ($_POST['accion'] ?? '');

    $diasPost = entero($_POST['dias_actuales'] ?? null) ?? 60;
    $diasRetencion = in_array($diasPost, $diasPermitidos, true) ? $diasPost : 60;

    $estadoPost = trim((string) ($_POST['estado_actual'] ?? ''));
    $filtroEstado = in_array($estadoPost, $estadosPermitidos, true) ? $estadoPost : '';

    if ($accion === 'eliminar_uno') {
        $idEliminar = entero($_POST['id'] ?? null) ?? 0;
        $tituloEliminado = '';

        foreach ($registrosHistoricos as $idx => $item) {
            if ($item['id'] !== $idEliminar) {
                continue;
            }

            try {
                $fechaVencimiento = new DateTime($item['fecha_vencimiento']);
                $hoyValidacion = new DateTime('today');
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
                'tipo' => 'exito',
                'titulo' => 'Convocatoria eliminada',
                'texto' => "Se eliminó «{$tituloEliminado}» de la base de datos.",
            ];
        } else {
            $alerta = [
                'tipo' => 'error',
                'titulo' => 'No fue posible eliminar',
                'texto' => 'El registro no existe, no es elegible o la base de datos no permitió su eliminación.',
            ];
        }
    } elseif ($accion === 'eliminar_lote') {
        $rawSeleccion = is_array($_POST['seleccionados'] ?? null) ? $_POST['seleccionados'] : [];
        // Filtramos solo enteros positivos válidos, eliminamos duplicados y limitamos el lote a 100 elementos
        $idsSeleccionados = array_slice(
            array_values(array_unique(array_filter(array_map('intval', $rawSeleccion), static fn(int $id): bool => $id > 0))),
            0,
            100
        );

        if (empty($idsSeleccionados)) {
            $alerta = [
                'tipo' => 'alerta',
                'titulo' => 'Sin selección válida',
                'texto' => 'Selecciona al menos un registro vencido en la tabla para ejecutar la eliminación masiva.',
            ];
        } else {
            $eliminados = 0;
            $hoyLote = new DateTime('today');

            foreach ($registrosHistoricos as $idx => $item) {
                if (!in_array($item['id'], $idsSeleccionados, true)) {
                    continue;
                }

                try {
                    $fechaVencimiento = new DateTime($item['fecha_vencimiento']);
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
                'tipo' => $eliminados > 0 ? 'exito' : 'error',
                'titulo' => 'Eliminación por lote completada',
                'texto' => $eliminados > 0
                    ? "Se eliminaron {$eliminados} convocatoria(s) vencida(s) de la base de datos."
                    : 'Ningún registro fue eliminado. Puede estar protegido o no ser elegible.',
            ];
        }
    }
}

$hoy = new DateTime('today');
$candidatos = [];

foreach ($registrosHistoricos as $item) {
    $fechaVenc = new DateTime($item['fecha_vencimiento']);
    $diasTranscurridos = ($fechaVenc < $hoy) ? (int) $hoy->diff($fechaVenc)->days : 0;

    if ($diasTranscurridos < $diasRetencion) {
        continue;
    }
    if ($filtroEstado !== '' && $item['estado'] !== $filtroEstado) {
        continue;
    }

    $item['dias_transcurridos'] = $diasTranscurridos;
    $candidatos[] = $item;
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
    <h1 class="sin-margen">Eliminación de becas y expedientes por eliminación</h1>
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