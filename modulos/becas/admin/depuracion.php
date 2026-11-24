<?php
/**
 * MÓDULO BECAS · Depuración de convocatorias y expedientes vencidos
 * Explorador Vocacional UTBB
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

// 1. Verificación de rol obligatoria
requerir_rol('editor', 'coordinacion');

$diasPermitidos    = [30, 60, 90, 180];
$estadosPermitidos = ['cerrada', 'aceptada_vencida', 'rechazada'];

$registrosHistoricos = [
    [
        'id' => 101,
        'categoria' => 'Convocatoria',
        'titulo' => 'Estímulo a Proyectos de Innovación Tecnológica 2026-1',
        'detalle' => 'Convocatoria cerrada · 34 expedientes adjuntos',
        'fecha_vencimiento' => '2026-05-30',
        'estado' => 'cerrada',
    ],
    [
        'id' => 102,
        'categoria' => 'Expediente / Archivo',
        'titulo' => 'Solicitud #2026-089 — Karla Mendoza Ruiz',
        'detalle' => 'Beca Socioeconómica (Ciclo Ene-Abr) · PDF Kardex + Comprobante',
        'fecha_vencimiento' => '2026-04-15',
        'estado' => 'aceptada_vencida',
    ],
    [
        'id' => 103,
        'categoria' => 'Expediente / Archivo',
        'titulo' => 'Solicitud #2026-114 — Luis Fernando Ortega',
        'detalle' => 'Beca de Movilidad · Documentación incompleta',
        'fecha_vencimiento' => '2026-08-20',
        'estado' => 'rechazada',
    ],
    [
        'id' => 104,
        'categoria' => 'Convocatoria',
        'titulo' => 'Apoyo Emergente de Conectividad 2026',
        'detalle' => 'Convocatoria cerrada · 19 expedientes adjuntos',
        'fecha_vencimiento' => '2026-09-10',
        'estado' => 'cerrada',
    ],
    [
        'id' => 105,
        'categoria' => 'Expediente / Archivo',
        'titulo' => 'Solicitud #2026-203 — Valeria Soto Pérez',
        'detalle' => 'Beca de Excelencia · Vigencia recién concluida',
        'fecha_vencimiento' => '2026-10-30',
        'estado' => 'aceptada_vencida',
    ],
];

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
            if ($item['id'] === $idEliminar) {
                $tituloEliminado = $item['titulo'];
                unset($registrosHistoricos[$idx]);
                break;
            }
        }

        if ($tituloEliminado !== '') {
            $alerta = [
                'tipo' => 'exito',
                'titulo' => 'Registro eliminado (simulación)',
                'texto' => "Se eliminó «{$tituloEliminado}» y sus archivos asociados de la vista actual.",
            ];
        } else {
            $alerta = [
                'tipo' => 'error',
                'titulo' => 'Identificador no válido',
                'texto' => 'El registro indicado no existe o no es elegible para depuración.',
            ];
        }
    } elseif ($accion === 'eliminar_lote') {
        $rawSeleccion = is_array($_POST['seleccionados'] ?? null) ? $_POST['seleccionados'] : [];
        // Filtramos solo enteros positivos válidos y limitamos el lote a 100 elementos
        $idsSeleccionados = array_slice(
            array_values(array_filter(array_map('intval', $rawSeleccion), static fn(int $id): bool => $id > 0)),
            0,
            100
        );

        if (empty($idsSeleccionados)) {
            $alerta = [
                'tipo' => 'alerta',
                'titulo' => 'Sin selección válida',
                'texto' => 'Selecciona al menos un registro vencido en la tabla para ejecutar la depuración masiva.',
            ];
        } else {
            $eliminados = 0;
            foreach ($registrosHistoricos as $idx => $item) {
                if (in_array($item['id'], $idsSeleccionados, true)) {
                    unset($registrosHistoricos[$idx]);
                    $eliminados++;
                }
            }

            $alerta = [
                'tipo' => 'exito',
                'titulo' => 'Eliminación por lote completada (simulación)',
                'texto' => "Se eliminaron {$eliminados} registro(s) vencido(s) y sus archivos temporales sin tocar la base de datos.",
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

cabecera('Depuración · Becas y apoyos', '', '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li><a href="index.php">Becas y apoyos</a></li>
      <li>Depuración</li>
    </ol>
  </nav>

  <header class="fila fila--separada mb-6">
    <h1 class="sin-margen">Depuración de becas y expedientes</h1>
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
        <label class="campo__etiqueta" for="estado">Motivo de depuración</label>
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
      <h2>Sin registros por depurar</h2>
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
          <strong><?= count($candidatos) ?></strong> registro(s) con &ge; <?= (int) $diasRetencion ?> días vencidos.
        </p>
        <button class="boton boton--peligro boton--pequeno" type="submit">Depurar seleccionados</button>
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
                         class="check-depuracion" aria-label="Seleccionar <?= e($item['titulo']) ?>">
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
  document.querySelectorAll('.check-depuracion').forEach(function (chk) {
    chk.checked = e.target.checked;
  });
});
</script>

<?php
pie();