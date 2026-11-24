<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';

// 1. Acceso: solo el rol aspirante puede postular
requerir_rol('aspirante');

// Integrante 2 · Postulación. Sin base de datos: la solicitud aún no se registra; los documentos sí se guardan en uploads/.

// 2. Catálogos y valores iniciales
$becas = [
    'academica'       => 'Beca Académica',
    'socioeconomica'  => 'Beca Socioeconómica',
    'extracurricular' => 'Apoyo Extracurricular',
    'alimenticia'     => 'Beca Alimenticia',
    'federal'         => 'Jóvenes Escribiendo el Futuro (Federal)',
];

$carreras = ['TI', 'Mercadotecnia', 'Negocios', 'Mantenimiento Industrial', 'Gastronomía', 'Turismo', 'Otra'];

$idBeca = get('id', 'academica');
if (!isset($becas[$idBeca])) {
    $idBeca = 'academica';
}

$errores = [];
$errorGeneral = '';
$enviado = false;
$folio   = '';

$datos = [
    'beca'      => $idBeca,
    'nombre'    => (string)(usuario()['nombre'] ?? ''),
    'matricula' => '',
    'correo'    => (string)(usuario()['correo'] ?? ''),
    'telefono'  => '',
    'carrera'   => '',
    'cuatri'    => '',
    'promedio'  => '',
    'motivo'    => '',
];
$acepta = false;

// Documentos requeridos: se guardan en uploads/ con nombre generado por el servidor.
$documentos = [
    'doc_identificacion' => 'identificación o credencial',
    'doc_kardex'         => 'kardex o constancia de calificaciones',
];
$tiposDocs = ['application/pdf', 'image/jpeg', 'image/png'];

// 3. Resultado de un envío anterior (POST-Redirect-GET)
if (isset($_SESSION['bec_envio']) && is_array($_SESSION['bec_envio'])) {
    $datos['beca']   = (string)($_SESSION['bec_envio']['beca'] ?? $idBeca);
    $datos['correo'] = (string)($_SESSION['bec_envio']['correo'] ?? '');
    $folio           = (string)($_SESSION['bec_envio']['folio'] ?? '');
    $enviado         = isset($becas[$datos['beca']]) && $folio !== '';
    unset($_SESSION['bec_envio']);
}

// 4. Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si el envío supera post_max_size, PHP vacía $_POST y $_FILES
    if ($_POST === [] && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errorGeneral = 'El envío supera el tamaño que el servidor permite recibir. Cada documento debe pesar máximo 2 MB; vuelve a llenar el formulario.';
    } else {
        verificar_token();

        foreach ($datos as $campo => $_) {
            $datos[$campo] = post($campo);
        }
        $acepta = post('acepto') === '1';

        // Validación de campos
        if (!isset($becas[$datos['beca']])) {
            $errores['beca'] = 'Selecciona una beca válida.';
        }
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) < 5) {
            $errores['nombre'] = 'Escribe tu nombre completo.';
        }
        $datos['matricula'] = strtoupper($datos['matricula']);
        if (!preg_match('/^([A-Z]{1,5}-?)?[0-9]{6,10}$/', $datos['matricula'])) {
            $errores['matricula'] = 'Escribe tu matrícula con las iniciales de tu carrera y tus números (ej. TI-2201044).';
        }
        if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'Escribe un correo electrónico válido.';
        }
        if ($datos['telefono'] !== '' && (
                !preg_match('/^[0-9 ()-]+$/', $datos['telefono'])
                || strlen(preg_replace('/\D/', '', $datos['telefono'])) !== 10
            )) {
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

        // Validación de documentos
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

        // Guardado de documentos (solo si todo lo anterior es válido)
        if ($errores === []) {
            $guardados = [];
            foreach ($documentos as $input => $nombreDoc) {
                try {
                    $guardados[] = guardar_archivo($_FILES[$input], __DIR__ . '/uploads', $tiposDocs, 2);
                } catch (RuntimeException $ex) {
                    $errores[$input] = $ex->getMessage();
                    break;
                }
            }
            if ($errores !== []) {
                foreach ($guardados as $nombreArchivo) {
                    @unlink(__DIR__ . '/uploads/' . $nombreArchivo);
                }
            }
        }

        // Envío correcto: redirigir
        if ($errores === []) {
            // Folio simulado.
            $folio = 'BEC-' . date('Y') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);

            $_SESSION['bec_envio'] = [
                'folio'  => $folio,
                'beca'   => $datos['beca'],
                'correo' => $datos['correo'],
            ];
            redirigir('solicitar.php?id=' . rawurlencode($datos['beca']));
        }
    }
}

// 5. Funciones auxiliares de la vista
function bec_clase_campo(array $errores, string $campo): string
{
    return 'campo' . (isset($errores[$campo]) ? ' campo--invalido' : '');
}
function bec_aria(array $errores, string $campo): string
{
    return isset($errores[$campo])
        ? ' aria-invalid="true" aria-describedby="error-' . e($campo) . '"'
        : '';
}
function bec_error(array $errores, string $campo): void
{
    if (isset($errores[$campo])) {
        echo '<span class="campo__error" id="error-' . e($campo) . '">' . e($errores[$campo]) . '</span>';
    }
}

// 6. Vista
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

  <?php /* Confirmación */ ?>
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

    <?php if ($errorGeneral !== ''): ?>
      <div class="alerta alerta--error" role="alert">
        <div>
          <p class="alerta__titulo">No se pudo enviar tu solicitud</p>
          <p><?= e($errorGeneral) ?></p>
        </div>
      </div>
    <?php elseif ($errores !== []): ?>
      <div class="alerta alerta--error" role="alert">
        <div>
          <p class="alerta__titulo">Revisa tu solicitud</p>
          <p>Hay <?= count($errores) ?> campo(s) por corregir. Los marcamos en rojo.</p>
        </div>
      </div>
    <?php endif; ?>

    <?php /* Formulario */ ?>
    <form class="formulario bec-formulario" method="POST" action="solicitar.php?id=<?= e($idBeca) ?>" enctype="multipart/form-data" novalidate>
      <?= campo_token() ?>

      <fieldset class="grupo-campos">
        <legend class="grupo-campos__titulo">1. Beca a la que postulas</legend>

        <div class="<?= bec_clase_campo($errores, 'beca') ?>">
          <label class="campo__etiqueta" for="beca">Beca o apoyo <span class="campo__requerido">*</span></label>
          <select class="campo__control" id="beca" name="beca"<?= bec_aria($errores, 'beca') ?> required>
            <?php foreach ($becas as $clave => $titulo): ?>
              <option value="<?= e($clave) ?>" <?= $datos['beca'] === $clave ? 'selected' : '' ?>><?= e($titulo) ?></option>
            <?php endforeach; ?>
          </select>
          <?php bec_error($errores, 'beca'); ?>
        </div>
      </fieldset>

      <fieldset class="grupo-campos">
        <legend class="grupo-campos__titulo">2. Datos del alumno</legend>

        <div class="<?= bec_clase_campo($errores, 'nombre') ?>">
          <label class="campo__etiqueta" for="nombre">Nombre completo <span class="campo__requerido">*</span></label>
          <input class="campo__control" type="text" id="nombre" name="nombre"<?= bec_aria($errores, 'nombre') ?> value="<?= e($datos['nombre']) ?>" autocomplete="name" required>
          <?php bec_error($errores, 'nombre'); ?>
        </div>

        <div class="bec-fila">
          <div class="<?= bec_clase_campo($errores, 'matricula') ?>">
            <label class="campo__etiqueta" for="matricula">Matrícula <span class="campo__requerido">*</span></label>
            <input class="campo__control" type="text" id="matricula" name="matricula"<?= bec_aria($errores, 'matricula') ?> placeholder="Ej. TI-2201044" value="<?= e($datos['matricula']) ?>" required>
            <?php bec_error($errores, 'matricula'); ?>
          </div>

          <div class="<?= bec_clase_campo($errores, 'telefono') ?>">
            <label class="campo__etiqueta" for="telefono">Teléfono</label>
            <input class="campo__control" type="tel" id="telefono" name="telefono"<?= bec_aria($errores, 'telefono') ?> value="<?= e($datos['telefono']) ?>" autocomplete="tel">
            <?php bec_error($errores, 'telefono'); ?>
          </div>
        </div>

        <div class="<?= bec_clase_campo($errores, 'correo') ?>">
          <label class="campo__etiqueta" for="correo">Correo electrónico <span class="campo__requerido">*</span></label>
          <input class="campo__control" type="email" id="correo" name="correo"<?= bec_aria($errores, 'correo') ?> value="<?= e($datos['correo']) ?>" autocomplete="email" placeholder="usuario@utbb.edu.mx" required>
          <?php bec_error($errores, 'correo'); ?>
        </div>

        <div class="bec-fila">
          <div class="<?= bec_clase_campo($errores, 'carrera') ?>">
            <label class="campo__etiqueta" for="carrera">Carrera <span class="campo__requerido">*</span></label>
            <select class="campo__control" id="carrera" name="carrera"<?= bec_aria($errores, 'carrera') ?> required>
              <option value="">Selecciona…</option>
              <?php foreach ($carreras as $c): ?>
                <option value="<?= e($c) ?>" <?= $datos['carrera'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
            <?php bec_error($errores, 'carrera'); ?>
          </div>

          <div class="<?= bec_clase_campo($errores, 'cuatri') ?>">
            <label class="campo__etiqueta" for="cuatri">Cuatrimestre <span class="campo__requerido">*</span></label>
            <input class="campo__control" type="number" id="cuatri" name="cuatri"<?= bec_aria($errores, 'cuatri') ?> min="2" max="11" value="<?= e($datos['cuatri']) ?>" required>
            <?php bec_error($errores, 'cuatri'); ?>
          </div>

          <div class="<?= bec_clase_campo($errores, 'promedio') ?>">
            <label class="campo__etiqueta" for="promedio">Promedio <span class="campo__requerido">*</span></label>
            <input class="campo__control" type="number" id="promedio" name="promedio"<?= bec_aria($errores, 'promedio') ?> min="0" max="10" step="0.1" value="<?= e($datos['promedio']) ?>" required>
            <?php bec_error($errores, 'promedio'); ?>
          </div>
        </div>

        <div class="<?= bec_clase_campo($errores, 'motivo') ?>">
          <label class="campo__etiqueta" for="motivo">¿Por qué solicitas esta beca? <span class="campo__requerido">*</span></label>
          <textarea class="campo__control" id="motivo" name="motivo"<?= bec_aria($errores, 'motivo') ?> required><?= e($datos['motivo']) ?></textarea>
          <span class="campo__ayuda">Mínimo 20 caracteres.</span>
          <?php bec_error($errores, 'motivo'); ?>
        </div>
      </fieldset>

      <fieldset class="grupo-campos">
        <legend class="grupo-campos__titulo">3. Documentos</legend>

        <div class="<?= bec_clase_campo($errores, 'doc_identificacion') ?>">
          <label class="campo__etiqueta" for="doc_identificacion">Identificación o credencial <span class="campo__requerido">*</span></label>
          <input class="campo__control" type="file" id="doc_identificacion" name="doc_identificacion"<?= bec_aria($errores, 'doc_identificacion') ?> accept=".pdf,.jpg,.jpeg,.png" required>
          <span class="campo__ayuda">PDF, JPG o PNG. Máximo 2 MB.</span>
          <?php bec_error($errores, 'doc_identificacion'); ?>
        </div>

        <div class="<?= bec_clase_campo($errores, 'doc_kardex') ?>">
          <label class="campo__etiqueta" for="doc_kardex">Kardex o constancia de calificaciones <span class="campo__requerido">*</span></label>
          <input class="campo__control" type="file" id="doc_kardex" name="doc_kardex"<?= bec_aria($errores, 'doc_kardex') ?> accept=".pdf,.jpg,.jpeg,.png" required>
          <span class="campo__ayuda">PDF, JPG o PNG. Máximo 2 MB.</span>
          <?php bec_error($errores, 'doc_kardex'); ?>
        </div>
      </fieldset>

      <div class="<?= bec_clase_campo($errores, 'acepto') ?>">
        <label class="opcion">
          <input type="checkbox" name="acepto" value="1" <?= $acepta ? 'checked' : '' ?> required<?= bec_aria($errores, 'acepto') ?>>
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