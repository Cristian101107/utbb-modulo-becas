<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';

$id   = (int) get('id', '0');
$beca = $id > 0 ? bec_obtener_programa($id) : null;

if ($beca === null) {
    no_encontrado('La beca solicitada no existe.');
}

$esFederal    = ($beca['tipo'] === 'federal');
$etiquetaTipo = $esFederal ? 'Programa Federal' : (BEC_TIPOS[$beca['tipo']] ?? 'Beca Institucional');
$estadoActual = (string) ($beca['estado'] ?? ((int) $beca['activa'] === 1 ? 'activa' : 'cerrada'));
$textoEstado  = BEC_ESTADOS[$estadoActual] ?? 'Activa';
$claseEstado  = $estadoActual === 'activa' ? 'insignia--publicado' : ($estadoActual === 'proxima' ? 'insignia--aviso' : 'insignia--error');

cabecera($beca['nombre'] . ' — Becas UTBB', '', 'css/modulo.css');
?>

<header class="portada bec-portada">
  <div class="contenedor">
    <span class="insignia <?= e($claseEstado) ?> mb-4"><?= e($textoEstado) ?></span>
    <h1><?= e($beca['nombre']) ?></h1>
    <p class="entradilla"><?= e($beca['descripcion']) ?></p>
  </div>
</header>

<main class="contenedor seccion">
  <?php pintar_mensajes(); ?>

  <article class="panel">
    <h2>Detalles del apoyo</h2>
    <ul>
      <li><strong>Modalidad:</strong> <?= e($etiquetaTipo) ?></li>
      <li><strong>Aplica para:</strong> <?= e((string) ($beca['carrera'] ?? 'Todas las carreras')) ?></li>
      <li><strong>Beneficio:</strong> <?= e($beca['monto_apoyo']) ?></li>
      <li><strong>Promedio mínimo requerido:</strong> <?= e(number_format((float) $beca['promedio_minimo'], 1)) ?></li>
      <?php if (!empty($beca['cupo'])): ?>
        <li><strong>Cupo disponible:</strong> <?= (int) $beca['cupo'] ?> lugares</li>
      <?php endif; ?>
      <li>
        <strong>Vigencia de convocatoria:</strong>
        <time datetime="<?= e($beca['fecha_inicio']) ?>"><?= e(date('d/m/Y', strtotime($beca['fecha_inicio']))) ?></time>
        al
        <time datetime="<?= e($beca['fecha_cierre']) ?>"><?= e(date('d/m/Y', strtotime($beca['fecha_cierre']))) ?></time>
      </li>
      <li><strong>Documentación adjunta:</strong> <?= (int) ($beca['requiere_documentos'] ?? 1) === 1 ? 'Requiere adjuntar PDF o imagen en línea' : 'No requiere adjuntar archivos en línea' ?></li>
    </ul>

    <h3 class="mt-6">Requisitos principales</h3>
    <?php if (empty($beca['requisitos'])): ?>
      <p>Consulta los requisitos directamente en el departamento de becas.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($beca['requisitos'] as $req): ?>
          <li><?= e($req['descripcion'] ?? '') ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <footer class="fila mt-6">
      <?php if ($estadoActual === 'activa'): ?>
        <a class="boton boton--primario" href="solicitar.php?id=<?= (int) $beca['id'] ?>">Iniciar solicitud</a>
      <?php else: ?>
        <span class="boton boton--secundario" aria-disabled="true">Convocatoria <?= e(mb_strtolower($textoEstado)) ?></span>
      <?php endif; ?>
      <a class="boton boton--secundario" href="index.php">Volver al catálogo</a>
    </footer>
  </article>

  <footer class="bec-contacto">
    <span><strong>Atención:</strong> Edificio de Biblioteca</span>
    <span><strong>Teléfono:</strong> (+52) 322 226 8300 Ext. 1502</span>
    <span><strong>Correo:</strong> becas@utbb.edu.mx</span>
  </footer>
</main>

<?php
pie();