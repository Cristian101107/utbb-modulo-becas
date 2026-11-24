<?php
/**
 * MÓDULO BECAS · Panel de administración
 * Explorador Vocacional UTBB
 *
 * La verificación de rol es la PRIMERA instrucción ejecutable.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

if (file_exists(__DIR__ . '/../includes/consultas.php')) {
    require_once __DIR__ . '/../includes/consultas.php';
}

// Catálogo de estados (combina los de consultas.php si existen con los de becas)
$estadosCatalogo = defined('PLT_ESTADOS') ? PLT_ESTADOS : [
    'borrador' => 'Borrador',
    'proxima'  => 'Próxima',
    'activa'   => 'Activa',
    'cerrada'  => 'Cerrada',
];

// Sanitización y lista blanca en filtros GET
$busqueda  = mb_substr(trim((string) get('q')), 0, 100);
$estadoRaw = trim((string) get('estado'));
$estado    = array_key_exists($estadoRaw, $estadosCatalogo) ? $estadoRaw : '';

// Un editor solo ve lo de su carrera (restricción aplicada en el servidor).
$carreraId = (rol() === 'editor') ? carrera_del_usuario() : entero(get('carrera'));
$pagina    = max(1, entero(get('pagina')) ?? 1);

$modoSimulado = false;

try {
    if (!function_exists('plt_listar')) {
        throw new RuntimeException('Función plt_listar aún no disponible.');
    }
    $resultado = plt_listar($busqueda, $estado, $carreraId, $pagina);
} catch (Throwable $e) {
    // Respaldo simulado mientras la tabla SQL del módulo aún no existe en XAMPP
    $modoSimulado = true;
    $simulados = [
        [
            'id'             => 1,
            'carrera_id'     => 1,
            'carrera'        => 'Tecnologías de la Información',
            'titulo'         => 'Beca de Excelencia Académica',
            'monto'          => '$4,500.00 MXN / cuatrimestre',
            'apertura'       => '2026-09-01',
            'cierre'         => '2026-09-20',
            'estado'         => 'activa',
            'actualizado_en' => '2026-11-15 10:30:00',
        ],
        [
            'id'             => 2,
            'carrera_id'     => 1,
            'carrera'        => 'Tecnologías de la Información',
            'titulo'         => 'Apoyo de Manutención y Transporte',
            'monto'          => '$2,800.00 MXN / bimestre',
            'apertura'       => '2026-10-05',
            'cierre'         => '2026-10-25',
            'estado'         => 'activa',
            'actualizado_en' => '2026-11-16 14:15:00',
        ],
        [
            'id'             => 3,
            'carrera_id'     => 3,
            'carrera'        => 'Médico Cirujano',
            'titulo'         => 'Beca de Movilidad e Intercambio Internacional',
            'monto'          => '$18,000.00 MXN / pago único',
            'apertura'       => '2026-11-15',
            'cierre'         => '2026-12-10',
            'estado'         => 'proxima',
            'actualizado_en' => '2026-11-18 09:00:00',
        ],
        [
            'id'             => 4,
            'carrera_id'     => 2,
            'carrera'        => 'Mantenimiento Industrial',
            'titulo'         => 'Estímulo a Proyectos de Innovación Tecnológica',
            'monto'          => '$6,000.00 MXN / proyecto',
            'apertura'       => '2026-05-01',
            'cierre'         => '2026-05-30',
            'estado'         => 'cerrada',
            'actualizado_en' => '2026-06-01 12:00:00',
        ],
    ];

    $filasFiltradas = array_values(array_filter($simulados, static function (array $f) use ($busqueda, $estado, $carreraId): bool {
        if ($carreraId !== null && (int) $f['carrera_id'] !== (int) $carreraId) {
            return false;
        }
        if ($busqueda !== '' && mb_stripos($f['titulo'], $busqueda) === false && mb_stripos($f['carrera'], $busqueda) === false) {
            return false;
        }
        if ($estado !== '' && $f['estado'] !== $estado) {
            return false;
        }
        return true;
    }));

    $resultado = [
        'filas' => $filasFiltradas,
        'total' => count($filasFiltradas),
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
          <?= $modoSimulado ? ' (vista previa sin base de datos)' : '' ?>
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
            <tr>
              <td><strong><?= e($f['titulo']) ?></strong></td>
              <td><?= e($f['carrera'] ?? 'General') ?></td>
              <td><?= e($f['monto'] ?? '—') ?></td>
              <td>
                <?php if (!empty($f['apertura']) && !empty($f['cierre'])): ?>
                  <time datetime="<?= e($f['apertura']) ?>"><?= e(date('d/m/Y', strtotime($f['apertura']))) ?></time>
                  –
                  <time datetime="<?= e($f['cierre']) ?>"><?= e(date('d/m/Y', strtotime($f['cierre']))) ?></time>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <?php
                $claveEstado  = (string) ($f['estado'] ?? 'borrador');
                $claseEstado  = function_exists('plt_clase_estado')
                    ? plt_clase_estado($claveEstado)
                    : 'beca-estado beca-estado--' . $claveEstado;
                $textoEstado  = $estadosCatalogo[$claveEstado] ?? $claveEstado;
                ?>
                <span class="<?= e($claseEstado) ?>"><?= e($textoEstado) ?></span>
              </td>
              <td>
                <?php if (!empty($f['actualizado_en'])): ?>
                  <time datetime="<?= e($f['actualizado_en']) ?>">
                    <?= e(date('d/m/Y H:i', strtotime($f['actualizado_en']))) ?>
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