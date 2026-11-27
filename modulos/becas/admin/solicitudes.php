<?php
require_once __DIR__ . '/../../../nucleo/comun.php';
requerir_rol('editor', 'coordinacion');
require_once __DIR__ . '/../includes/consultas.php';

$estado = trim((string) get('estado'));
if (!array_key_exists($estado, BEC_ESTADOS_SOLICITUD)) {
    $estado = '';
}

$carreraId   = (rol() === 'editor') ? carrera_del_usuario() : null;
$solicitudes = bec_bandeja_comite($estado, $carreraId);

cabecera('Bandeja del comité · Becas', '', '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li><a href="index.php">Convocatorias</a></li>
      <li>Bandeja del comité</li>
    </ol>
  </nav>

  <header class="fila fila--separada mb-6">
    <h1 class="sin-margen">Solicitudes de becas (Comité)</h1>
    <a class="boton boton--secundario" href="index.php">← Volver a convocatorias</a>
  </header>

  <?php pintar_mensajes(); ?>

  <form class="panel mb-6" method="GET" action="">
    <fieldset class="fila becas-fieldset">
      <p class="campo sin-margen">
        <label class="campo__etiqueta" for="estado">Filtrar por estado</label>
        <select class="campo__control" id="estado" name="estado">
          <option value="">Todos</option>
          <option value="enviada" <?= $estado === 'enviada' ? 'selected' : '' ?>>Enviada</option>
          <option value="en_revision" <?= $estado === 'en_revision' ? 'selected' : '' ?>>En revisión</option>
          <option value="aprobada" <?= $estado === 'aprobada' ? 'selected' : '' ?>>Aprobada</option>
          <option value="rechazada" <?= $estado === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
        </select>
      </p>
      <button class="boton boton--secundario" type="submit">Filtrar</button>
    </fieldset>
  </form>

  <?php if (empty($solicitudes)): ?>
    <article class="vacio">
      <h2>No hay solicitudes</h2>
      <p>Cuando los alumnos envíen solicitudes aparecerán en esta bandeja.</p>
    </article>
  <?php else: ?>
    <figure class="tabla-envoltura sin-margen">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Folio</th>
            <th scope="col">Alumno</th>
            <th scope="col">Programa</th>
            <th scope="col">Promedio</th>
            <th scope="col">Estado</th>
            <th scope="col">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($solicitudes as $s): ?>
            <tr>
              <td><strong><?= e($s['folio']) ?></strong></td>
              <td>
                <strong><?= e($s['alumno']) ?></strong>
                <small>(<?= e($s['carrera']) ?>)</small>
              </td>
              <td><?= e($s['programa']) ?></td>
              <td><?= e((string) $s['promedio_actual']) ?></td>
              <td>
                <span class="beca-estado beca-estado--<?= e($s['estado']) ?>">
                  <?= e(BEC_ESTADOS_SOLICITUD[$s['estado']] ?? $s['estado']) ?>
                </span>
              </td>
              <td>
                <a class="boton boton--primario boton--pequeno" href="revisar.php?id=<?= (int) $s['id'] ?>">Revisar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </figure>
  <?php endif; ?>

</section>

<?php pie(); ?>