<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';

// Pantalla pública: se consulta con el folio y el correo de la cuenta que envió la solicitud.
$folio     = strtoupper(get('folio'));
$correo    = get('correo');
$consulto  = isset($_GET['folio']) || isset($_GET['correo']);
$solicitud = null;
$error     = '';

if ($consulto) {
    if ($folio === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Escribe tu folio y un correo electrónico válido.';
    } else {
        $solicitud = bec_consultar_seguimiento($folio, $correo);
        if ($solicitud === null) {
            // Mismo mensaje si el folio no existe o el correo no coincide.
            $error = 'No encontramos una solicitud con ese folio y correo. Revisa que sean los mismos de tu envío.';
        }
    }
}

cabecera('Seguimiento de Becas', '', 'css/modulo.css');
?>

<section class="portada bec-portada">
  <div class="contenedor">
    <span class="insignia insignia--info mb-4">Convocatorias UTBB</span>
    <h1>Seguimiento de Becas y Apoyos Estudiantiles</h1>
    <p class="entradilla">Consulta el estado de tu solicitud de beca o apoyo estudiantil.</p>
  </div>
</section>

<form class="contenedor seccion" method="GET" action="seguimiento.php">
  <h2>Consultar estado de solicitud</h2>
  <div class="campo">
    <label class="campo__etiqueta" for="folio">Número de folio</label>
    <input class="campo__control" type="text" id="folio" name="folio" placeholder="Ej. BEC-2026-00001" value="<?= e($folio) ?>" maxlength="30" required>
    <label class="campo__etiqueta" for="correo">Correo electrónico</label>
    <input class="campo__control" type="email" id="correo" name="correo" placeholder="Ej. usuario@utbb.edu.mx" value="<?= e($correo) ?>" maxlength="160" required>
  </div>

  <button class="boton boton--primario boton--bloque" type="submit">Consultar estado</button>
</form>

<?php if ($error !== ''): ?>
  <div class="contenedor seccion">
    <div class="alerta alerta--error" role="alert"><div><p><?= e($error) ?></p></div></div>
  </div>
<?php elseif ($solicitud !== null): ?>
  <div class="contenedor seccion">
    <h2>Resultados de la consulta</h2>
    <p class="leyenda">Mostrando resultados para el folio: <strong><?= e($solicitud['folio']) ?></strong></p>

    <div class="resultado-consulta">
      <p><strong>Estado de la solicitud:</strong> <?= e(BEC_ESTADOS_SOLICITUD[$solicitud['estado']] ?? $solicitud['estado']) ?></p>
      <p><strong>Fecha de solicitud:</strong> <?= e($solicitud['enviado_en'] ? date('d/m/Y', strtotime($solicitud['enviado_en'])) : 'Sin enviar') ?></p>
      <p><strong>Última actualización:</strong> <?= e(date('d/m/Y', strtotime($solicitud['actualizado_en']))) ?></p>
      <p><strong>Beca o apoyo:</strong> <?= e($solicitud['programa']) ?></p>
      <p><strong>Beneficio:</strong> <?= e($solicitud['monto_apoyo']) ?></p>
      <?php if ($solicitud['observaciones'] !== null): ?>
        <p><strong>Observaciones del comité:</strong> <?= e($solicitud['observaciones']) ?></p>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php
pie();
