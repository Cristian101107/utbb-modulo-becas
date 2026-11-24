<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';

$q         = trim(get('q', ''));
$tipo      = trim(get('tipo', ''));
$modalidad = trim(get('modalidad', ''));

$programas = bec_listar_programas($q, $tipo, $modalidad);

cabecera('Catálogo de Becas y Apoyos', '', 'css/modulo.css');
?>

<section class="portada bec-portada">
  <div class="contenedor">
    <span class="insignia insignia--info mb-4">Convocatorias UTBB</span>
    <h1>Becas y Apoyos Estudiantiles</h1>
    <p class="entradilla">Programas institucionales disponibles a partir de 2do. cuatrimestre como descuento en colegiatura y apoyos federales vigentes.</p>
  </div>
</section>

<section class="contenedor seccion">
  <?php pintar_mensajes(); ?>

  <div class="disposicion-filtros">

    <form class="panel" method="GET" action="index.php">
      <h2>Filtrar becas</h2>

      <div class="campo">
        <label class="campo__etiqueta" for="q">Buscar</label>
        <input class="campo__control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Ej. Académica, Alimenticia...">
      </div>

      <div class="campo">
        <label class="campo__etiqueta" for="tipo">Tipo de beca</label>
        <select class="campo__control" id="tipo" name="tipo">
          <option value="">Todos los tipos</option>
          <option value="academica" <?= $tipo === 'academica' ? 'selected' : '' ?>>Académica</option>
          <option value="socioeconomica" <?= $tipo === 'socioeconomica' ? 'selected' : '' ?>>Socioeconómica</option>
          <option value="deportiva" <?= $tipo === 'deportiva' ? 'selected' : '' ?>>Apoyo Extracurricular</option>
          <option value="alimenticia" <?= $tipo === 'alimenticia' ? 'selected' : '' ?>>Alimenticia</option>
          <option value="federal" <?= $tipo === 'federal' ? 'selected' : '' ?>>Federal (Benito Juárez)</option>
        </select>
      </div>

      <div class="campo">
        <label class="campo__etiqueta" for="modalidad">Modalidad de apoyo</label>
        <select class="campo__control" id="modalidad" name="modalidad">
          <option value="">Todas</option>
          <option value="colegiatura" <?= $modalidad === 'colegiatura' ? 'selected' : '' ?>>Descuento en colegiatura</option>
          <option value="especie" <?= $modalidad === 'especie' ? 'selected' : '' ?>>En especie (Cafetería)</option>
          <option value="economico" <?= $modalidad === 'economico' ? 'selected' : '' ?>>Estímulo federal</option>
        </select>
      </div>

      <button class="boton boton--primario boton--bloque" type="submit">Aplicar filtros</button>
      <?php if ($q !== '' || $tipo !== '' || $modalidad !== ''): ?>
        <a class="boton boton--secundario boton--bloque mt-4" href="index.php">Limpiar filtros</a>
      <?php endif; ?>
      <a class="boton boton--secundario boton--bloque mt-4" href="seguimiento.php">Seguimiento de solicitud</a>
    </form>

    <div>
      <p class="leyenda">Mostrando <?= count($programas) ?> programa(s) de apoyo disponibles</p>

      <?php if (empty($programas)): ?>
        <div class="panel">
          <p>No se encontraron becas con los filtros seleccionados.</p>
        </div>
      <?php else: ?>
        <div class="rejilla">
          <?php foreach ($programas as $p): ?>
            <?php
              $esFederal = ($p['tipo'] === 'federal');
              $etiquetaTipo = $esFederal ? 'Programa Federal' : 'Beca Institucional';
              $claseEstado  = $esFederal ? 'insignia--info' : 'insignia--publicado';
              $textoEstado  = $esFederal ? 'Federal' : 'Abierta';
            ?>
            <a class="tarjeta bec-tarjeta" href="ficha.php?id=<?= (int)$p['id'] ?>">
              <div class="tarjeta__cuerpo">
                <span class="bec-etiqueta-tipo"><?= e($etiquetaTipo) ?></span>
                <h3 class="tarjeta__titulo"><?= e($p['nombre']) ?></h3>
                <p class="leyenda"><?= e($p['monto_apoyo']) ?></p>
                <p><?= e($p['descripcion']) ?></p>
              </div>
              <div class="tarjeta__pie">
                <span class="insignia <?= e($claseEstado) ?>"><?= e($textoEstado) ?></span>
                <span>Ver detalles</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="bec-contacto">
        <span><strong>Atención:</strong> Edificio de Biblioteca</span>
        <span><strong>Teléfono:</strong> (+52) 322 226 8300 Ext. 1502</span>
        <span><strong>Correo:</strong> becas@utbb.edu.mx</span>
      </div>
    </div>

  </div>
</section>

<?php
pie();