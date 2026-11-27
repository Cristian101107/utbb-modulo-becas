<?php
require_once __DIR__ . '/../../../nucleo/comun.php';
requerir_rol('editor', 'coordinacion');
require_once __DIR__ . '/../includes/consultas.php';

$id  = entero(get('id')) ?? 0;
$sol = bec_obtener_solicitud_admin($id);

if (!$sol) {
    no_encontrado('Solicitud no encontrada.');
}

if (rol() === 'editor' && (int) $sol['carrera_id'] !== carrera_del_usuario()) {
    requerir_carrera((int) $sol['carrera_id']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();

    $estadoNuevo   = trim((string) post('estado_nuevo'));
    $observaciones = trim((string) post('observaciones'));
    $validos       = ['en_revision', 'aprobada', 'rechazada'];

    if (!in_array($estadoNuevo, $validos, true)) {
        $error = 'Selecciona un estado válido.';
    } elseif ($observaciones === '') {
        $error = 'Escribe el motivo del dictamen.';
    } else {
        bec_guardar_dictamen($id, (int) usuario()['id'], $sol['estado'], $estadoNuevo, $observaciones);
        mensaje('exito', 'Dictamen guardado correctamente.');
        redirigir('revisar.php?id=' . $id);
    }
}

cabecera('Revisar solicitud · ' . $sol['folio'], '', '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li><a href="solicitudes.php">Bandeja del comité</a></li>
      <li><?= e($sol['folio']) ?></li>
    </ol>
  </nav>

  <header class="fila fila--separada mb-6">
    <h1 class="sin-margen">Expediente <?= e($sol['folio']) ?></h1>
    <a class="boton boton--secundario" href="solicitudes.php">← Volver a bandeja</a>
  </header>

  <?php pintar_mensajes(); ?>

  <?php if ($error !== ''): ?>
    <aside class="alerta alerta--error mb-6" role="alert">
      <p><?= e($error) ?></p>
    </aside>
  <?php endif; ?>

  <article class="panel mb-6">
    <h2>Datos del alumno</h2>
    <dl>
      <dt><strong>Nombre y correo</strong></dt>
      <dd><?= e($sol['alumno']) ?> (<?= e($sol['correo']) ?>)</dd>

      <dt><strong>Carrera</strong></dt>
      <dd><?= e($sol['carrera']) ?> · <?= e($sol['matricula'] ?? '—') ?></dd>

      <dt><strong>Programa y promedio</strong></dt>
      <dd><?= e($sol['programa']) ?> · <?= e((string) $sol['promedio_actual']) ?> (Mínimo: <?= e((string) $sol['promedio_minimo']) ?>)</dd>

      <dt><strong>Estado actual</strong></dt>
      <dd><?= e(BEC_ESTADOS_SOLICITUD[$sol['estado']] ?? $sol['estado']) ?></dd>

      <dt><strong>Motivo del alumno</strong></dt>
      <dd><?= e($sol['motivo']) ?></dd>
    </dl>
  </article>

  <article class="panel mb-6">
    <h2>Documentos adjuntos</h2>
    <?php if (empty($sol['documentos'])): ?>
      <p>No hay documentos subidos.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($sol['documentos'] as $doc): ?>
          <li class="mb-2">
            <strong><?= e($doc['tipo_documento']) ?></strong>
            <a class="boton boton--secundario boton--pequeno" href="descargar.php?id=<?= (int) $doc['id'] ?>" target="_blank" rel="noopener">Ver / Descargar</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>

  <form class="formulario mb-6" method="POST" action="">
    <?= campo_token() ?>
    <fieldset class="grupo-campos">
      <legend class="grupo-campos__titulo">Emitir dictamen</legend>

      <p class="campo">
        <label class="campo__etiqueta" for="estado_nuevo">Resolución</label>
        <select class="campo__control" id="estado_nuevo" name="estado_nuevo" required>
          <option value="en_revision" <?= $sol['estado'] === 'en_revision' ? 'selected' : '' ?>>En revisión</option>
          <option value="aprobada" <?= $sol['estado'] === 'aprobada' ? 'selected' : '' ?>>Aprobada</option>
          <option value="rechazada" <?= $sol['estado'] === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
        </select>
      </p>

      <p class="campo">
        <label class="campo__etiqueta" for="observaciones">Motivo / Observaciones del comité</label>
        <textarea class="campo__control" id="observaciones" name="observaciones" rows="4" required></textarea>
      </p>
    </fieldset>
    <button class="boton boton--primario" type="submit">Guardar dictamen</button>
  </form>

  <article class="panel">
    <h2>Historial de dictámenes</h2>
    <?php if (empty($sol['dictamenes'])): ?>
      <p>Sin dictámenes registrados todavía.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($sol['dictamenes'] as $d): ?>
          <li class="mb-2">
            <p class="sin-margen">
              <strong><?= e(BEC_ESTADOS_SOLICITUD[$d['estado_nuevo']] ?? $d['estado_nuevo']) ?></strong>
              por <?= e($d['revisor'] ?? 'Comité') ?>
              (<time datetime="<?= e($d['creado_en']) ?>"><?= e($d['creado_en']) ?></time>)
            </p>
            <p><?= e($d['observaciones']) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>

</section>

<?php pie(); ?>