<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';

$id = (int)get('id', '0');
$beca = $id > 0 ? bec_obtener_programa($id) : null;

if ($beca === null) {
    no_encontrado('La beca solicitada no existe.');
}

$esFederal    = ($beca['tipo'] === 'federal');
$etiquetaTipo = $esFederal ? 'Programa Federal' : 'Beca Institucional';
$claseEstado  = $esFederal ? 'insignia--info' : 'insignia--publicado';
$textoEstado  = $esFederal ? 'Federal' : 'Abierta';

cabecera($beca['nombre'] . ' — Becas UTBB', '', 'css/modulo.css');
?>

<section class="portada bec-portada">
  <div class="contenedor">
    <span class="insignia <?= e($claseEstado) ?> mb-4"><?= e($textoEstado) ?></span>
    <h1><?= e($beca['nombre']) ?></h1>
    <p class="entradilla"><?= e($beca['descripcion']) ?></p>
  </div>
</section>

<main class="contenedor seccion">
  <?php pintar_mensajes(); ?>

  <div class="panel">
    <h2>Detalles del apoyo</h2>
    <p><strong>Modalidad:</strong> <?= e($etiquetaTipo) ?></p>
    <p><strong>Beneficio:</strong> <?= e($beca['monto_apoyo']) ?></p>
    <p><strong>Promedio mínimo requerido:</strong> <?= e((string)$beca['promedio_minimo']) ?></p>
    <p><strong>Vigencia de convocatoria:</strong> <?= e(date('d/m/Y', strtotime($beca['fecha_inicio']))) ?> al <?= e(date('d/m/Y', strtotime($beca['fecha_cierre']))) ?></p>

    <h3 class="mt-6">Requisitos principales</h3>
    <?php if (empty($beca['requisitos'])): ?>
      <p>Consulta los requisitos directamente en el departamento de becas.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($beca['requisitos'] as $req): ?>
          <li>
            <strong><?= e($req['nombre']) ?>:</strong>
            <?= e($req['descripcion'] ?? '') ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="mt-6">
      <a class="boton boton--primario" href="solicitar.php?id=<?= (int)$beca['id'] ?>">Iniciar solicitud</a>
      <a class="boton boton--secundario" href="index.php">Volver al catálogo</a>
    </div>
  </div>

  <div class="bec-contacto">
    <span><strong>Atención:</strong> Edificio de Biblioteca</span>
    <span><strong>Teléfono:</strong> (+52) 322 226 8300 Ext. 1502</span>
    <span><strong>Correo:</strong> becas@utbb.edu.mx</span>
  </div>
</main>

<?php
pie();