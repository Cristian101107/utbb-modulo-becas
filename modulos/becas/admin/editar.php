<?php
/**
 * MÓDULO BECAS · Alta y modificación de convocatorias
 * Explorador Vocacional UTBB
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

if (file_exists(__DIR__ . '/../includes/consultas.php')) {
    require_once __DIR__ . '/../includes/consultas.php';
}

const CARPETA_SUBIDAS = __DIR__ . '/../uploads';

$categoriasPermitidas = [
    'Mérito Académico',
    'Socioeconómica',
    'Movilidad',
    'Investigación',
    'Deportiva / Cultural',
];

$estadosPermitidos = defined('BEC_ESTADOS')
    ? BEC_ESTADOS
    : (defined('PLT_ESTADOS') ? PLT_ESTADOS : [
        'borrador' => 'Borrador',
        'proxima'  => 'Próxima',
        'activa'   => 'Activa',
        'cerrada'  => 'Cerrada',
    ]);

$plantillasBase = [
    'estandar' => [
        'titulo'     => 'Convocatoria Institucional de Apoyo Estudiantil 2026',
        'tipo'       => 'Socioeconómica',
        'monto'      => '$3,000.00 MXN / cuatrimestre',
        'apertura'   => date('Y-m-d'),
        'cierre'     => date('Y-m-d', strtotime('+20 days')),
        'estado'     => array_key_exists('proxima', $estadosPermitidos) ? 'proxima' : array_key_first($estadosPermitidos),
        'resumen'    => 'Apoyo económico cuatrimestral dirigido a estudiantes con necesidad socioeconómica comprobable.',
        'requisitos' => "- Estar inscrito en el periodo escolar vigente.\n- Promedio mínimo de 8.0 sin adeudos administrativos.\n- Solicitud firmada y copia de identificación oficial.\n- Comprobante de ingresos familiares reciente.",
        'cuerpo'     => 'Consulta las bases completas y entrega tu expediente digital dentro de las fechas establecidas.',
    ],
    'excelencia' => [
        'titulo'     => 'Beca al Mérito y Rendimiento Académico 2026',
        'tipo'       => 'Mérito Académico',
        'monto'      => '$5,000.00 MXN / cuatrimestre',
        'apertura'   => date('Y-m-d'),
        'cierre'     => date('Y-m-d', strtotime('+15 days')),
        'estado'     => array_key_exists('proxima', $estadosPermitidos) ? 'proxima' : array_key_first($estadosPermitidos),
        'resumen'    => 'Reconocimiento económico al desempeño académico sobresaliente de estudiantes regulares.',
        'requisitos' => "- Promedio mínimo de 9.5 en el ciclo inmediato anterior.\n- Haber cursado la carga académica completa.\n- Carta de exposición de motivos.",
        'cuerpo'     => 'El comité evaluador validará el historial académico oficial emitido por Servicios Escolares.',
    ],
];

$fnObtener    = function_exists('bec_obtener') ? 'bec_obtener' : (function_exists('plt_obtener') ? 'plt_obtener' : null);
$fnCrear      = function_exists('bec_crear') ? 'bec_crear' : (function_exists('plt_crear') ? 'plt_crear' : null);
$fnActualizar = function_exists('bec_actualizar') ? 'bec_actualizar' : (function_exists('plt_actualizar') ? 'plt_actualizar' : null);
$fnHistorial  = function_exists('bec_historial') ? 'bec_historial' : (function_exists('plt_historial') ? 'plt_historial' : null);

$id       = entero(get('id'));
$registro = null;

if ($id !== null) {
    if ($fnObtener === null) {
        no_encontrado('No está disponible la función de consulta de convocatorias.');
    }
    try {
        $registro = $fnObtener($id);
    } catch (Throwable $e) {
        error_log('Error al obtener convocatoria #' . $id . ': ' . $e->getMessage());
        $registro = null;
    }

    if ($registro === null) {
        no_encontrado('La convocatoria no existe.');
    }
}

// Un editor solo trabaja sobre su carrera.
if ($registro !== null && isset($registro['carrera_id']) && $registro['carrera_id'] !== null) {
    requerir_carrera((int) $registro['carrera_id']);
}

$estadoDefecto = array_key_exists('proxima', $estadosPermitidos) ? 'proxima' : (string) array_key_first($estadosPermitidos);

$datos = [
    'carrera_id' => $registro['carrera_id'] ?? (carrera_del_usuario() ?? ''),
    'titulo'     => $registro['titulo']     ?? '',
    'tipo'       => $registro['tipo']       ?? $registro['categoria'] ?? 'Mérito Académico',
    'monto'      => $registro['monto']      ?? '',
    'apertura'   => $registro['apertura']   ?? $registro['fecha_apertura'] ?? '',
    'cierre'     => $registro['cierre']     ?? $registro['fecha_cierre']   ?? '',
    'estado'     => $registro['estado']     ?? $estadoDefecto,
    'resumen'    => $registro['resumen']    ?? '',
    'requisitos' => $registro['requisitos'] ?? '',
    'cuerpo'     => $registro['cuerpo']     ?? '',
    'imagen'     => $registro['imagen']     ?? null,
];

$errores    = [];
$alertaInfo = null;

$plantillaSolicitada = trim((string) get('plantilla'));
if ($registro === null && $plantillaSolicitada !== '' && array_key_exists($plantillaSolicitada, $plantillasBase)) {
    $datos = array_merge($datos, $plantillasBase[$plantillaSolicitada]);
    $alertaInfo = 'Plantilla base cargada. Ajusta montos, fechas o requisitos antes de guardar.';
}

$validarFechaIso = static function (string $fecha): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt !== false && $dt->format('Y-m-d') === $fecha;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verificar_token();

    $limpiar = static fn(string $v): string => trim(str_replace("\0", '', $v));

    $datos['carrera_id'] = entero(post('carrera_id'));
    $datos['titulo']     = $limpiar((string) post('titulo'));
    $datos['tipo']       = $limpiar((string) post('tipo'));
    $datos['monto']      = $limpiar((string) post('monto'));
    $datos['apertura']   = $limpiar((string) post('apertura'));
    $datos['cierre']     = $limpiar((string) post('cierre'));
    $datos['estado']     = $limpiar((string) post('estado'));
    $datos['resumen']    = $limpiar((string) post('resumen'));
    $datos['requisitos'] = $limpiar((string) post('requisitos'));
    $datos['cuerpo']     = $limpiar((string) post('cuerpo'));

    // Alias por si consultas.php espera los nombres con prefijo fecha_ o categoria
    $datos['categoria']      = $datos['tipo'];
    $datos['fecha_apertura'] = $datos['apertura'];
    $datos['fecha_cierre']   = $datos['cierre'];

    if ($datos['carrera_id'] === null || carrera($datos['carrera_id']) === null) {
        $errores['carrera_id'] = 'Selecciona una carrera válida.';
    } else {
        requerir_carrera($datos['carrera_id']);
    }

    if ($datos['titulo'] === '') {
        $errores['titulo'] = 'El título es obligatorio.';
    } elseif (mb_strlen($datos['titulo']) > 160) {
        $errores['titulo'] = 'El título no puede pasar de 160 caracteres.';
    }

    if (!in_array($datos['tipo'], $categoriasPermitidas, true)) {
        $errores['tipo'] = 'Selecciona una categoría de beca válida.';
    }

    if ($datos['monto'] === '') {
        $errores['monto'] = 'El monto y periodicidad son obligatorios.';
    } elseif (mb_strlen($datos['monto']) > 80) {
        $errores['monto'] = 'El monto no puede exceder 80 caracteres.';
    }

    if (!$validarFechaIso($datos['apertura'])) {
        $errores['apertura'] = 'Indica una fecha de apertura válida.';
    }

    if (!$validarFechaIso($datos['cierre'])) {
        $errores['cierre'] = 'Indica una fecha de cierre válida.';
    } elseif (!isset($errores['apertura']) && $datos['cierre'] < $datos['apertura']) {
        $errores['cierre'] = 'La fecha de cierre no puede ser anterior a la de apertura.';
    }

    if (!array_key_exists($datos['estado'], $estadosPermitidos)) {
        $errores['estado'] = 'Selecciona un estado válido.';
    }

    if ($datos['requisitos'] === '') {
        $errores['requisitos'] = 'Captura al menos un requisito para los aspirantes.';
    } elseif (mb_strlen($datos['requisitos']) > 3000) {
        $errores['requisitos'] = 'Los requisitos no pueden exceder 3,000 caracteres.';
    }

    if (mb_strlen($datos['resumen']) > 400) {
        $errores['resumen'] = 'El resumen no puede pasar de 400 caracteres.';
    }

    if (!$errores) {
        $imagenAnterior = $registro['imagen'] ?? null;
        $imagenNueva    = null;

        try {
            if (!empty($_FILES['imagen']['name'])) {
                $imagenNueva     = guardar_archivo($_FILES['imagen'], CARPETA_SUBIDAS, SOLO_IMAGEN, 2);
                $datos['imagen'] = $imagenNueva;
            }

            if ($registro === null) {
                if ($fnCrear === null) {
                    throw new RuntimeException('No está disponible bec_crear() ni plt_crear() en consultas.php.');
                }
                $fnCrear($datos, usuario()['id']);
                mensaje('exito', 'Convocatoria creada correctamente.');
            } else {
                if ($fnActualizar === null) {
                    throw new RuntimeException('No está disponible bec_actualizar() ni plt_actualizar() en consultas.php.');
                }
                $fnActualizar((int) $registro['id'], $datos);
                mensaje('exito', 'Cambios guardados correctamente.');
            }

            if ($imagenNueva !== null && $imagenAnterior && $imagenAnterior !== $imagenNueva) {
                @unlink(CARPETA_SUBIDAS . '/' . basename($imagenAnterior));
            }

            redirigir('index.php');
        } catch (Throwable $e) {
            if ($imagenNueva !== null) {
                @unlink(CARPETA_SUBIDAS . '/' . basename($imagenNueva));
                $datos['imagen'] = $imagenAnterior;
            }
            error_log('Error al guardar convocatoria: ' . $e->getMessage());
            $errores['general'] = 'No fue posible guardar la convocatoria en la base de datos.'
                . ((defined('DESARROLLO') && DESARROLLO) ? ' (' . $e->getMessage() . ')' : '');
        }
    }
}

cabecera($registro ? 'Editar convocatoria' : 'Nueva convocatoria', '', '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li><a href="index.php">Becas y apoyos</a></li>
      <li><?= $registro ? 'Editar' : 'Nueva' ?></li>
    </ol>
  </nav>

  <header class="fila fila--separada mb-6">
    <h1 class="sin-margen"><?= $registro ? 'Editar convocatoria' : 'Nueva convocatoria' ?></h1>

    <?php if ($registro === null): ?>
      <nav class="becas-herramientas" aria-label="Cargar plantilla base">
        <span class="becas-etiqueta-plantilla">Plantilla base:</span>
        <a class="boton boton--secundario boton--pequeno" href="editar.php?plantilla=estandar">Apoyo general</a>
        <a class="boton boton--secundario boton--pequeno" href="editar.php?plantilla=excelencia">Excelencia</a>
      </nav>
    <?php endif; ?>
  </header>

  <?php if ($errores): ?>
    <aside class="alerta alerta--error mb-6" role="alert">
      <p><span class="alerta__titulo">Revisa los datos.</span>
         Hay <?= count($errores) ?> campo<?= count($errores) === 1 ? '' : 's' ?> con problemas.</p>
      <?php if (isset($errores['general'])): ?><p><?= e($errores['general']) ?></p><?php endif; ?>
    </aside>
  <?php elseif ($alertaInfo !== null): ?>
    <aside class="alerta alerta--exito mb-6" role="status">
      <p><span class="alerta__titulo">Listo.</span> <?= e($alertaInfo) ?></p>
    </aside>
  <?php endif; ?>

  <form class="formulario" method="POST" action="" enctype="multipart/form-data" novalidate>
    <?= campo_token() ?>

    <fieldset class="grupo-campos">
      <legend class="grupo-campos__titulo">Datos generales de la convocatoria</legend>

      <p class="campo <?= isset($errores['carrera_id']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="carrera_id">
          Carrera <span class="campo__requerido" aria-hidden="true">*</span>
        </label>
        <select class="campo__control" id="carrera_id" name="carrera_id" required
                <?= rol() === 'editor' ? 'disabled' : '' ?>>
          <option value="">Selecciona una opción</option>
          <?php foreach (carreras() as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $datos['carrera_id'] === (int) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (rol() === 'editor'): ?>
          <input type="hidden" name="carrera_id" value="<?= (int) carrera_del_usuario() ?>">
          <small class="campo__ayuda">Como editor, trabajas sobre tu carrera.</small>
        <?php endif; ?>
        <?php if (isset($errores['carrera_id'])): ?>
          <small class="campo__error"><?= e($errores['carrera_id']) ?></small>
        <?php endif; ?>
      </p>

      <p class="campo <?= isset($errores['titulo']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="titulo">
          Título de la convocatoria <span class="campo__requerido" aria-hidden="true">*</span>
        </label>
        <input class="campo__control" type="text" id="titulo" name="titulo"
               placeholder="Ej. Beca de Excelencia Académica 2026"
               value="<?= e((string) $datos['titulo']) ?>" maxlength="160" required>
        <?php if (isset($errores['titulo'])): ?>
          <small class="campo__error"><?= e($errores['titulo']) ?></small>
        <?php endif; ?>
      </p>

      <fieldset class="becas-grid becas-grid--2 becas-fieldset">
        <p class="campo <?= isset($errores['tipo']) ? 'campo--invalido' : '' ?>">
          <label class="campo__etiqueta" for="tipo">
            Categoría <span class="campo__requerido" aria-hidden="true">*</span>
          </label>
          <select class="campo__control" id="tipo" name="tipo">
            <?php foreach ($categoriasPermitidas as $cat): ?>
              <option value="<?= e($cat) ?>" <?= $datos['tipo'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errores['tipo'])): ?>
            <small class="campo__error"><?= e($errores['tipo']) ?></small>
          <?php endif; ?>
        </p>

        <p class="campo <?= isset($errores['monto']) ? 'campo--invalido' : '' ?>">
          <label class="campo__etiqueta" for="monto">
            Monto y periodicidad <span class="campo__requerido" aria-hidden="true">*</span>
          </label>
          <input class="campo__control" type="text" id="monto" name="monto"
                 placeholder="Ej. $3,500.00 MXN / cuatrimestre"
                 value="<?= e((string) $datos['monto']) ?>" maxlength="80" required>
          <?php if (isset($errores['monto'])): ?>
            <small class="campo__error"><?= e($errores['monto']) ?></small>
          <?php endif; ?>
        </p>
      </fieldset>

      <fieldset class="becas-grid becas-grid--3 becas-fieldset">
        <p class="campo <?= isset($errores['apertura']) ? 'campo--invalido' : '' ?>">
          <label class="campo__etiqueta" for="apertura">
            Fecha de apertura <span class="campo__requerido" aria-hidden="true">*</span>
          </label>
          <input class="campo__control" type="date" id="apertura" name="apertura"
                 value="<?= e((string) $datos['apertura']) ?>" required>
          <?php if (isset($errores['apertura'])): ?>
            <small class="campo__error"><?= e($errores['apertura']) ?></small>
          <?php endif; ?>
        </p>

        <p class="campo <?= isset($errores['cierre']) ? 'campo--invalido' : '' ?>">
          <label class="campo__etiqueta" for="cierre">
            Fecha de cierre <span class="campo__requerido" aria-hidden="true">*</span>
          </label>
          <input class="campo__control" type="date" id="cierre" name="cierre"
                 value="<?= e((string) $datos['cierre']) ?>" required>
          <?php if (isset($errores['cierre'])): ?>
            <small class="campo__error"><?= e($errores['cierre']) ?></small>
          <?php endif; ?>
        </p>

        <p class="campo <?= isset($errores['estado']) ? 'campo--invalido' : '' ?>">
          <label class="campo__etiqueta" for="estado">Estado</label>
          <select class="campo__control" id="estado" name="estado">
            <?php foreach ($estadosPermitidos as $clave => $etiqueta): ?>
              <option value="<?= e((string) $clave) ?>" <?= $datos['estado'] === $clave ? 'selected' : '' ?>><?= e((string) $etiqueta) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errores['estado'])): ?>
            <small class="campo__error"><?= e($errores['estado']) ?></small>
          <?php endif; ?>
        </p>
      </fieldset>
    </fieldset>

    <fieldset class="grupo-campos">
      <legend class="grupo-campos__titulo">Requisitos y difusión</legend>

      <p class="campo <?= isset($errores['requisitos']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="requisitos">
          Requisitos y documentación <span class="campo__requerido" aria-hidden="true">*</span>
        </label>
        <textarea class="campo__control" id="requisitos" name="requisitos" rows="5"
                  maxlength="3000" placeholder="Enlista un requisito por línea..." required><?= e((string) $datos['requisitos']) ?></textarea>
        <?php if (isset($errores['requisitos'])): ?>
          <small class="campo__error"><?= e($errores['requisitos']) ?></small>
        <?php endif; ?>
      </p>

      <p class="campo <?= isset($errores['resumen']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="resumen">Resumen</label>
        <textarea class="campo__control" id="resumen" name="resumen" rows="3"
                  maxlength="400"><?= e((string) $datos['resumen']) ?></textarea>
        <small class="campo__ayuda">Se muestra en la tarjeta del listado público. Máximo 400 caracteres.</small>
        <?php if (isset($errores['resumen'])): ?>
          <small class="campo__error"><?= e($errores['resumen']) ?></small>
        <?php endif; ?>
      </p>

      <p class="campo">
        <label class="campo__etiqueta" for="cuerpo">Bases y detalles adicionales</label>
        <textarea class="campo__control" id="cuerpo" name="cuerpo" rows="6"><?= e((string) $datos['cuerpo']) ?></textarea>
      </p>

      <p class="campo <?= isset($errores['imagen']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="imagen">Imagen o cartel promocional</label>
        <?php if (!empty($datos['imagen'])): ?>
          <img src="../uploads/<?= e((string) $datos['imagen']) ?>" alt="Imagen actual"
               style="max-width:260px;border-radius:6px;margin-bottom:8px">
        <?php endif; ?>
        <input class="campo__control" type="file" id="imagen" name="imagen"
               accept="image/jpeg,image/png,image/webp">
        <small class="campo__ayuda">JPG, PNG o WebP. Máximo 2 MB. El servidor comprueba el tipo real del archivo.</small>
        <?php if (isset($errores['imagen'])): ?>
          <small class="campo__error"><?= e($errores['imagen']) ?></small>
        <?php endif; ?>
      </p>
    </fieldset>

    <footer class="fila">
      <button class="boton boton--primario" type="submit">
        <?= $registro ? 'Guardar cambios' : 'Publicar convocatoria' ?>
      </button>
      <a class="boton boton--secundario" href="index.php">Cancelar</a>
    </footer>
  </form>

  <?php if ($registro && $fnHistorial !== null): ?>
    <h2 class="mt-6">Historial</h2>
    <?php
    $historial = [];
    try {
        $historial = $fnHistorial((int) $registro['id']);
    } catch (Throwable $e) {
        $historial = [];
    }
    ?>
    <?php if (!$historial): ?>
      <p class="leyenda">Sin cambios de estado registrados.</p>
    <?php else: ?>
      <figure class="tabla-envoltura sin-margen">
        <table class="tabla">
          <thead>
            <tr>
              <th scope="col">Fecha</th>
              <th scope="col">Cambio</th>
              <th scope="col">Quién</th>
              <th scope="col">Motivo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historial as $h): ?>
              <tr>
                <td><?= e(date('d/m/Y H:i', strtotime((string) $h['creado_en']))) ?></td>
                <td><?= e((string) ($h['estado_previo'] ?? '—')) ?> → <?= e((string) $h['estado_nuevo']) ?></td>
                <td><?= e((string) ($h['usuario'] ?? 'Sistema')) ?></td>
                <td><?= e((string) ($h['motivo'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </figure>
    <?php endif; ?>
  <?php endif; ?>

</section>

<?php
pie();