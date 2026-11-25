<?php
/**
 * MÓDULO BECAS · Panel de administración
 * Explorador Vocacional UTBB
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

if (file_exists(__DIR__ . '/../includes/consultas.php')) {
    require_once __DIR__ . '/../includes/consultas.php';
}

$estadosCatalogo = defined('BEC_ESTADOS')
    ? BEC_ESTADOS
    : (defined('PLT_ESTADOS') ? PLT_ESTADOS : [
        'borrador' => 'Borrador',
        'proxima'  => 'Próxima',
        'activa'   => 'Activa',
        'cerrada'  => 'Cerrada',
    ]);

$busqueda  = mb_substr(trim((string) get('q')), 0, 100);
$estadoRaw = trim((string) get('estado'));
$estado    = array_key_exists($estadoRaw, $estadosCatalogo) ? $estadoRaw : '';

// Un editor solo ve lo de su carrera.
$carreraId = (rol() === 'editor') ? carrera_del_usuario() : entero(get('carrera'));
$pagina    = max(1, entero(get('pagina')) ?? 1);

$errorCarga   = false;
$detalleError = '';

try {
    $fnListar = function_exists('bec_listar')
        ? 'bec_listar'
        : (function_exists('plt_listar') ? 'plt_listar' : null);

    if ($fnListar === null) {
        throw new RuntimeException('No se encontró bec_listar() ni plt_listar() en includes/consultas.php.');
    }

    $resultado = $fnListar($busqueda, $estado, $carreraId, $pagina);

    if (!is_array($resultado) || !isset($resultado['filas'], $resultado['total']) || !is_array($resultado['filas'])) {
        throw new RuntimeException('La consulta de convocatorias devolvió una estructura inválida.');
    }
} catch (Throwable $e) {
    $errorCarga   = true;
    $detalleError = $e->getMessage();
    error_log('Error al listar becas: ' . $detalleError);
    $resultado = [
        'filas' => [],
        'total' => 0,
    ];
}

cabecera('Panel · Becas y apoyos', '', '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li>Becas y apoyos</li>
    </ol>
  </nav>

  <header class="fila fila--separada mb-6">
    <h1 class="sin-margen">Convocatorias de becas</h1>
    <nav class="becas-herramientas" aria-label="Acciones del gestor">
      <a class="boton boton--secundario" href="<?= RUTA_BASE ?>/panel.php">← Volver al panel</a>
      <a class="boton boton--secundario" href="depuracion.php">Eliminación</a>
      <a class="boton boton--primario" href="editar.php">Nueva convocatoria</a>
    </nav>
  </header>

  <?php pintar_mensajes(); ?>

  <?php if ($errorCarga): ?>
    <aside class="alerta alerta--error mb-6" role="alert">
      <p>No fue posible cargar las convocatorias desde la base de datos. Verifica la conexión y la configuración del módulo.</p>
      <?php if (defined('DESARROLLO') && DESARROLLO && $detalleError !== ''): ?>
        <p><small>Detalle técnico: <?= e($detalleError) ?></small></p>
      <?php endif; ?>
    </aside>
  <?php endif; ?>

  <form class="panel mb-6" method="GET" action="">
    <fieldset class="fila becas-fieldset">
      <p class="campo sin-margen becas-campo-busqueda">
        <label class="campo__etiqueta" for="q">Buscar</label>
        <input class="campo__control" type="search" id="q" name="q" maxlength="100"
               value="<?= e($busqueda) ?>" placeholder="Título o carrera...">
      </p>

      <p class="campo sin-margen becas-campo-filtro">
        <label class="campo__etiqueta" for="estado">Estado</label>
        <select class="campo__control" id="estado" name="estado">
          <option value="">Todos</option>
          <?php foreach ($estadosCatalogo as $clave => $texto): ?>
            <option value="<?= e($clave) ?>" <?= $estado === $clave ? 'selected' : '' ?>><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
      </p>

      <button class="boton boton--secundario" type="submit">Filtrar</button>
    </fieldset>
  </form>

  <?php if (empty($resultado['filas'])): ?>

    <article class="vacio">
      <h2>No hay convocatorias</h2>
      <p class="ancho-lectura">Cuando captures la primera o ajustes los filtros aparecerá aquí.</p>
      <a class="boton boton--primario" href="editar.php">Crear la primera</a>
    </article>

  <?php else: ?>

    <figure class="tabla-envoltura sin-margen">
      <table class="tabla">
        <caption>
          <?= (int) $resultado['total'] ?> convocatoria<?= (int) $resultado['total'] === 1 ? '' : 's' ?>
        </caption>
        <thead>
          <tr>
            <th scope="col">Título</th>
            <th scope="col">Carrera</th>
            <th scope="col">Monto</th>
            <th scope="col">Vigencia</th>
            <th scope="col">Estado</th>
            <th scope="col">Actualizado</th>
            <th scope="col">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resultado['filas'] as $f): ?>
            <?php
            $apertura = $f['apertura'] ?? $f['fecha_apertura'] ?? $f['fecha_inicio'] ?? '';
            $cierre   = $f['cierre']   ?? $f['fecha_cierre']   ?? $f['fecha_fin']    ?? '';
            $monto    = $f['monto']    ?? $f['apoyo']          ?? '—';
            ?>
            <tr>
              <td><strong><?= e((string) ($f['titulo'] ?? '')) ?></strong></td>
              <td><?= e((string) ($f['carrera'] ?? 'General')) ?></td>
              <td><?= e((string) $monto) ?></td>
              <td>
                <?php if ($apertura !== '' && $cierre !== ''): ?>
                  <time datetime="<?= e((string) $apertura) ?>"><?= e(date('d/m/Y', strtotime((string) $apertura))) ?></time>
                  –
                  <time datetime="<?= e((string) $cierre) ?>"><?= e(date('d/m/Y', strtotime((string) $cierre))) ?></time>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <?php
                $claveEstado = (string) ($f['estado'] ?? 'borrador');
                if (function_exists('bec_clase_estado')) {
                    $claseEstado = bec_clase_estado($claveEstado);
                } elseif (function_exists('plt_clase_estado')) {
                    $claseEstado = plt_clase_estado($claveEstado);
                } else {
                    $claseEstado = 'beca-estado beca-estado--' . $claveEstado;
                }
                $textoEstado = $estadosCatalogo[$claveEstado] ?? $claveEstado;
                ?>
                <span class="<?= e($claseEstado) ?>"><?= e($textoEstado) ?></span>
              </td>
              <td>
                <?php if (!empty($f['actualizado_en'])): ?>
                  <time datetime="<?= e((string) $f['actualizado_en']) ?>">
                    <?= e(date('d/m/Y H:i', strtotime((string) $f['actualizado_en']))) ?>
                  </time>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <menu class="tabla__acciones">
                  <li>
                    <a class="boton boton--secundario boton--pequeno" href="editar.php?id=<?= (int) $f['id'] ?>">Editar</a>
                  </li>
                  <li>
                    <form method="POST" action="eliminar.php"
                          onsubmit="return confirm('¿Eliminar esta convocatoria? La acción no se puede deshacer.')">
                      <?= campo_token() ?>
                      <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                      <button class="boton boton--peligro boton--pequeno" type="submit">Eliminar</button>
                    </form>
                  </li>
                </menu>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </figure>

  <?php endif; ?>

</section>

<?php
pie();