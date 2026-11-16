<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';

/* ---------------------------------------------------------------------------
   Integrante 2 · Postulación
   Pantalla de registro y envío de solicitud del estudiante.
   Sin base de datos: se reciben los datos por $_POST y se muestra la
   alerta de confirmación. El guardado real se conecta después.
   ------------------------------------------------------------------------ */

// Becas disponibles (mismos id que usa ficha.php en ?id=)
$becas = [
    'academica'       => 'Beca Académica',
    'socioeconomica'  => 'Beca Socioeconómica',
    'extracurricular' => 'Apoyo Extracurricular',
    'alimenticia'     => 'Beca Alimenticia',
    'federal'         => 'Jóvenes Escribiendo el Futuro (Federal)',
];

$carreras = ['TI', 'Mercadotecnia', 'Negocios', 'Mantenimiento Industrial', 'Gastronomía', 'Turismo', 'Otra'];

// Beca preseleccionada si el alumno llega desde la ficha (?id=...)
$idBeca = get('id', 'academica');
if (!isset($becas[$idBeca])) {
    $idBeca = 'academica';
}

$errores = [];
$enviado = false;
$folio   = '';

// Valores del formulario (se conservan si hay errores)
$datos = [
    'beca'      => $idBeca,
    'nombre'    => '',
    'matricula' => '',
    'correo'    => '',
    'telefono'  => '',
    'carrera'   => '',
    'cuatri'    => '',
    'promedio'  => '',
    'motivo'    => '',
];

// Documentos que se piden. Solo se validan; NO se guardan en el servidor.
$documentos = [
    'doc_identificacion' => 'identificación o credencial',
    'doc_kardex'         => 'kardex o constancia de calificaciones',
];
$tiposDocs = ['application/pdf', 'image/jpeg', 'image/png'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();

    foreach ($datos as $campo => $_) {
        $datos[$campo] = post($campo);
    }
    $acepta = post('acepto') === '1';

    // --- Validación ---
    if (!isset($becas[$datos['beca']])) {
        $errores['beca'] = 'Selecciona una beca válida.';
    }
    if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) < 5) {
        $errores['nombre'] = 'Escribe tu nombre completo.';
    }
    // Formato: iniciales de la carrera (cualquiera, 1 a 5 letras), guion opcional y dígitos (ej. TI-2201044); también solo dígitos
    $datos['matricula'] = strtoupper($datos['matricula']);
    if (!preg_match('/^([A-Z]{1,5}-?)?[0-9]{6,10}$/', $datos['matricula'])) {
        $errores['matricula'] = 'Escribe tu matrícula con las iniciales de tu carrera y tus números (ej. TI-2201044).';
    }
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'Escribe un correo electrónico válido.';
    }
    if ($datos['telefono'] !== '' && !preg_match('/^[0-9 +()-]{10,18}$/', $datos['telefono'])) {
        $errores['telefono'] = 'Escribe un teléfono de 10 dígitos.';
    }
    if (!in_array($datos['carrera'], $carreras, true)) {
        $errores['carrera'] = 'Selecciona tu carrera.';
    }
    $cuatri = entero($datos['cuatri']);
    if ($cuatri === null || $cuatri < 2 || $cuatri > 11) {
        $errores['cuatri'] = 'Indica tu cuatrimestre (a partir del 2.º).';
    }
    if (!is_numeric($datos['promedio']) || (float)$datos['promedio'] < 0 || (float)$datos['promedio'] > 10) {
        $errores['promedio'] = 'Escribe un promedio entre 0 y 10.';
    }
    if (mb_strlen($datos['motivo']) < 20) {
        $errores['motivo'] = 'Cuéntanos tu motivo en al menos 20 caracteres.';
    }

    // Documentos: solo se valida que lleguen, su tamaño y su tipo real. No se guardan.
    foreach ($documentos as $input => $nombreDoc) {
        $archivo = $_FILES[$input] ?? null;

        if ($archivo === null || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            $errores[$input] = 'Adjunta tu ' . $nombreDoc . ' (PDF, JPG o PNG).';
        } elseif ($archivo['error'] !== UPLOAD_ERR_OK || $archivo['size'] > 2 * 1024 * 1024) {
            $errores[$input] = 'El archivo no se pudo recibir o pesa más de 2 MB.';
        } elseif (!in_array((new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']), $tiposDocs, true)) {
            $errores[$input] = 'El archivo debe ser PDF, JPG o PNG.';
        }
    }

    if (!$acepta) {
        $errores['acepto'] = 'Debes confirmar que tus datos son verídicos.';
    }

    if ($errores === []) {
        // Simulación del guardado: folio de ejemplo, sin tocar base de datos.
        $folio   = 'BEC-' . date('Y') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $enviado = true;
    }
}

/** Clase de campo con error + mensaje bajo el control. */
function bec_clase_campo(array $errores, string $campo): string
{
    return 'campo' . (isset($errores[$campo]) ? ' campo--invalido' : '');
}
function bec_error(array $errores, string $campo): void
{
    if (isset($errores[$campo])) {
        echo '<span class="campo__error" id="error-' . e($campo) . '">' . e($errores[$campo]) . '</span>';
    }
}

cabecera('Solicitar beca — Becas UTBB', '', 'css/modulo.css');
?>

<section class="portada bec-portada">
  <div class="contenedor">
    <span class="insignia insignia--info mb-4">Convocatorias UTBB</span>
    <h1>Solicitud de beca o apoyo</h1>
    <p class="entradilla">Completa tus datos, adjunta tus documentos y envía tu postulación.</p>
  </div>
</section>

<div class="contenedor seccion">

  <ol class="migas" aria-label="Ruta de navegación">
    <li><a href="index.php">Becas</a></li>
    <li><a href="ficha.php?id=<?= e($idBeca) ?>">Detalle</a></li>
    <li aria-current="page">Solicitar</li>
  </ol>

  <?php if ($enviado): ?>

    <div class="alerta alerta--exito" role="status">
      <div>
        <p class="alerta__titulo">¡Solicitud enviada!</p>
        <p>
          Recibimos tu postulación a <strong><?= e($becas[$datos['beca']]) ?></strong>.
          Tu folio es <strong><?= e($folio) ?></strong>; guárdalo para consultar el estado de tu solicitud.
          Te contactaremos a <strong><?= e($datos['correo']) ?></strong>.
        </p>
      </div>
    </div>

    <p>
      <a class="boton boton--primario" href="seguimiento.php">Consultar mi solicitud</a>
      <a class="boton boton--secundario" href="index.php">Volver al catálogo</a>
    </p>

  <?php else: ?>

    <?php if ($errores !== []): ?>
      <div class="alerta alerta--error" role="alert">
        <div>
          <p class="alerta__titulo">Revisa tu solicitud</p>
          <p>Hay <?= count($errores) ?> campo(s) por corregir. Los marcamos en rojo.</p>
        </div>
      </div>
    <?php endif; ?>

    <form class="formulario bec-formulario" method="POST" action="solicitar.php?id=<?= e($idBeca) ?>" enctype="multipart/form-data" novalidate>
      <?= campo_token() ?>

      <!-- 1. Beca -->
      <fieldset class="grupo-campos">
        <legend class="grupo-campos__titulo">1. Beca a la que postulas</legend>

        <div class="<?= bec_clase_campo($errores, 'beca') ?>">
          <label class="campo__etiqueta" for="beca">Beca o apoyo <span class="campo__requerido">*</span></label>
          <select class="campo__control" id="beca" name="beca" required>
            <?php foreach ($becas as $clave => $titulo): ?>
              <option value="<?= e($clave) ?>" <?= $datos['beca'] === $clave ? 'selected' : '' ?>><?= e($titulo) ?></option>
            <?php endforeach; ?>
          </select>
          <?php bec_error($errores, 'beca'); ?>
        </div>
      </fieldset>

      <!-- 2. Datos del alumno -->
      <fieldset class="grupo-campos">
        <legend class="grupo-campos__titulo">2. Datos del alumno</legend>

        <div class="<?= bec_clase_campo($errores, 'nombre') ?>">
          <label class="campo__etiqueta" for="nombre">Nombre completo <span class="campo__requerido">*</span></label>
          <input class="campo__control" type="text" id="nombre" name="nombre" value="<?= e($datos['nombre']) ?>" autocomplete="name" required>
          <?php bec_error($errores, 'nombre'); ?>
        </div>

        <div class="bec-fila">
          <div class="<?= bec_clase_campo($errores, 'matricula') ?>">
            <label class="campo__etiqueta" for="matricula">Matrícula <span class="campo__requerido">*</span></label>
            <input class="campo__control" type="text" id="matricula" name="matricula" placeholder="Ej. TI-2201044" value="<?= e($datos['matricula']) ?>" required>
            <?php bec_error($errores, 'matricula'); ?>
          </div>

          <div class="<?= bec_clase_campo($errores, 'telefono') ?>">
            <label class="campo__etiqueta" for="telefono">Teléfono</label>
            <input class="campo__control" type="tel" id="telefono" name="telefono" value="<?= e($datos['telefono']) ?>" autocomplete="tel">
            <?php bec_error($errores, 'telefono'); ?>
          </div>
        </div>

        <div class="<?= bec_clase_campo($errores, 'correo') ?>">
          <label class="campo__etiqueta" for="correo">Correo electrónico <span class="campo__requerido">*</span></label>
          <input class="campo__control" type="email" id="correo" name="correo" value="<?= e($datos['correo']) ?>" autocomplete="email" placeholder="usuario@utbb.edu.mx" required>
          <?php bec_error($errores, 'correo'); ?>
        </div>

        <div class="bec-fila">
          <div class="<?= bec_clase_campo($errores, 'carrera') ?>">
            <label class="campo__etiqueta" for="carrera">Carrera <span class="campo__requerido">*</span></label>
            <select class="campo__control" id="carrera" name="carrera" required>
              <option value="">Selecciona…</option>
              <?php foreach ($carreras as $c): ?>
                <option value="<?= e($c) ?>" <?= $datos['carrera'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
            <?php bec_error($errores, 'carrera'); ?>
          </div>

          <div class="<?= bec_clase_campo($errores, 'cuatri') ?>">
            <label class="campo__etiqueta" for="cuatri">Cuatrimestre <span class="campo__requerido">*</span></label>
            <input class="campo__control" type="number" id="cuatri" name="cuatri" min="2" max="11" value="<?= e($datos['cuatri']) ?>" required>
            <?php bec_error($errores, 'cuatri'); ?>
          </div>

          <div class="<?= bec_clase_campo($errores, 'promedio') ?>">
            <label class="campo__etiqueta" for="promedio">Promedio <span class="campo__requerido">*</span></label>
            <input class="campo__control" type="number" id="promedio" name="promedio" min="0" max="10" step="0.1" value="<?= e($datos['promedio']) ?>" required>
            <?php bec_error($errores, 'promedio'); ?>
          </div>
        </div>

        <div class="<?= bec_clase_campo($errores, 'motivo') ?>">
          <label class="campo__etiqueta" for="motivo">¿Por qué solicitas esta beca? <span class="campo__requerido">*</span></label>
          <textarea class="campo__control" id="motivo" name="motivo" required><?= e($datos['motivo']) ?></textarea>
          <span class="campo__ayuda">Mínimo 20 caracteres.</span>
          <?php bec_error($errores, 'motivo'); ?>
        </div>
      </fieldset>

      <!-- 3. Documentos -->
      <fieldset class="grupo-campos">
        <legend class="grupo-campos__titulo">3. Documentos</legend>

        <div class="<?= bec_clase_campo($errores, 'doc_identificacion') ?>">
          <label class="campo__etiqueta" for="doc_identificacion">Identificación o credencial <span class="campo__requerido">*</span></label>
          <input class="campo__control" type="file" id="doc_identificacion" name="doc_identificacion" accept=".pdf,.jpg,.jpeg,.png" required>
          <span class="campo__ayuda">PDF, JPG o PNG. Máximo 2 MB.</span>
          <?php bec_error($errores, 'doc_identificacion'); ?>
        </div>

        <div class="<?= bec_clase_campo($errores, 'doc_kardex') ?>">
          <label class="campo__etiqueta" for="doc_kardex">Kardex o constancia de calificaciones <span class="campo__requerido">*</span></label>
          <input class="campo__control" type="file" id="doc_kardex" name="doc_kardex" accept=".pdf,.jpg,.jpeg,.png" required>
          <span class="campo__ayuda">PDF, JPG o PNG. Máximo 2 MB.</span>
          <?php bec_error($errores, 'doc_kardex'); ?>
        </div>
      </fieldset>

      <!-- Confirmación y envío -->
      <div class="<?= bec_clase_campo($errores, 'acepto') ?>">
        <label class="opcion">
          <input type="checkbox" name="acepto" value="1">
          Confirmo que los datos y documentos que presento son verídicos.
        </label>
        <?php bec_error($errores, 'acepto'); ?>
      </div>

      <button class="boton boton--primario" type="submit">Enviar solicitud</button>
      <a class="boton boton--secundario" href="ficha.php?id=<?= e($idBeca) ?>">Cancelar</a>
    </form>

  <?php endif; ?>
</div>

<?php
pie();
