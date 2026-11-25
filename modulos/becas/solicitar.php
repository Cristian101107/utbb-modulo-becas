<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';

// 1. Acceso: solo el rol aspirante puede postular (primera instrucción del archivo)
requerir_rol('aspirante');

require_once __DIR__ . '/includes/consultas.php';

// Integrante 2 · Postulación. La solicitud se registra en bec_solicitudes y los
// documentos en bec_documentos (archivos en uploads/, con nombre generado por el servidor).

$usuario   = usuario();
$usuarioId = (int)$usuario['id'];

// 2. Catálogos desde la base de datos
$programas = bec_programas_abiertos();
$porId     = [];
foreach ($programas as $p) {
    $porId[(int)$p['id']] = $p;
}

$carreras      = carreras();
$carrerasPorId = array_column($carreras, 'nombre', 'id');

$idBeca = entero(get('id')) ?? 0;
if (!isset($porId[$idBeca])) {
    $idBeca = $porId === [] ? 0 : (int)array_key_first($porId);
}

$errores      = [];
$errorGeneral = '';
$enviado      = false;
$folio        = '';
$nombrePrograma = '';

// Nombre y correo salen de la cuenta (sesión), no del formulario.
$nombreCuenta = (string)($usuario['nombre'] ?? '');
$correoCuenta = (string)($usuario['correo'] ?? '');

$datos = [
    'beca'      => (string)$idBeca,
    'matricula' => '',
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

// Qué documento es cada campo (se guarda en bec_documentos.tipo_documento)
$tipoPorCampo = [
    'doc_identificacion' => 'identificacion',
    'doc_kardex'         => 'kardex',
];

// 3. Resultado de un envío anterior (POST-Redirect-GET)
if (isset($_SESSION['bec_envio']) && is_array($_SESSION['bec_envio'])) {
    $folio          = (string)($_SESSION['bec_envio']['folio'] ?? '');
    $nombrePrograma = (string)($_SESSION['bec_envio']['programa'] ?? '');
    $enviado        = $folio !== '' && $nombrePrograma !== '';
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

        // Programa: debe existir y tener la convocatoria abierta
        $programaId = entero($datos['beca']);
        $programa   = $programaId !== null ? ($porId[$programaId] ?? null) : null;
        if ($programa === null) {
            $errores['beca'] = 'Selecciona una beca con convocatoria abierta.';
        } else {
            $idBeca = $programaId;
            $previa = bec_solicitud_de_usuario($usuarioId, $programaId);
            if ($previa !== null) {
                $errores['beca'] = 'Ya enviaste una solicitud a esta beca (folio ' . $previa['folio'] . ').';
            }
        }

        // Matrícula
        $datos['matricula'] = strtoupper($datos['matricula']);
        if (!preg_match('/^([A-Z]{1,5}-?)?[0-9]{6,10}$/', $datos['matricula'])) {
            $errores['matricula'] = 'Escribe tu matrícula con las iniciales de tu carrera y tus números (ej. TI-2201044).';
        }

        // Teléfono (opcional): se guarda solo con dígitos
        $telefono = null;
        if ($datos['telefono'] !== '') {
            $digitos = preg_replace('/\D/', '', $datos['telefono']);
            if (!preg_match('/^[0-9 ()-]+$/', $datos['telefono']) || strlen($digitos) !== 10) {
                $errores['telefono'] = 'Escribe un teléfono de 10 dígitos.';
            } else {
                $telefono = $digitos;
            }
        }

        // Carrera: el valor enviado es el id de la tabla carreras
        $carreraId = entero($datos['carrera']);
        if ($carreraId === null || !isset($carrerasPorId[$carreraId])) {
            $errores['carrera'] = 'Selecciona tu carrera.';
        }

        // Cuatrimestre
        $cuatri = entero($datos['cuatri']);
        if ($cuatri === null || $cuatri < 2 || $cuatri > 11) {
            $errores['cuatri'] = 'Indica tu cuatrimestre (a partir del 2.º).';
        }

        // Promedio: máximo un decimal (la columna es DECIMAL(3,1)) y mínimo del programa
        $promedio = str_replace(',', '.', $datos['promedio']);
        if (!preg_match('/^(10(\.0)?|[0-9](\.[0-9])?)$/', $promedio)) {
            $errores['promedio'] = 'Escribe un promedio entre 0 y 10 con un solo decimal (ej. 9.2).';
        } elseif ($programa !== null && (float)$promedio < (float)$programa['promedio_minimo']) {
            $errores['promedio'] = 'Esta beca requiere un promedio mínimo de '
                . number_format((float)$programa['promedio_minimo'], 1) . '.';
        }

        // Motivo
        if (mb_strlen($datos['motivo']) < 20) {
            $errores['motivo'] = 'Cuéntanos tu motivo en al menos 20 caracteres.';
        } elseif (mb_strlen($datos['motivo']) > 2000) {
            $errores['motivo'] = 'El motivo no puede pasar de 2000 caracteres.';
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

        // Guardado de documentos en disco (solo si todo lo anterior es válido)
        $guardados = [];
        if ($errores === []) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            foreach ($documentos as $input => $nombreDoc) {
                $archivo = $_FILES[$input];
                try {
                    $mime      = (string)$finfo->file($archivo['tmp_name']);
                    $nombreSrv = guardar_archivo($archivo, __DIR__ . '/uploads', $tiposDocs, 2);

                    $guardados[] = [
                        'tipo_documento'  => $tipoPorCampo[$input],
                        'nombre_guardado' => $nombreSrv,
                        'tipo_mime'       => $mime,
                        'tamano_bytes'    => (int)$archivo['size'],
                    ];
                } catch (RuntimeException $ex) {
                    $errores[$input] = $ex->getMessage();
                    break;
                }
            }
        }

        // Registro en la base de datos (solicitud + documentos en una transacción)
        if ($errores === []) {
            try {
                $folio = bec_crear_solicitud([
                    'programa_id'  => $programaId,
                    'usuario_id'   => $usuarioId,
                    'carrera_id'   => $carreraId,
                    'matricula'    => $datos['matricula'],
                    'telefono'     => $telefono,
                    'cuatrimestre' => $cuatri,
                    'promedio'     => $promedio,
                    'motivo'       => $datos['motivo'],
                ], $guardados);
            } catch (PDOException $ex) {
                $folio = '';
                // 1062 = clave duplicada: el mismo alumno ya había enviado esta beca
                if ((int)($ex->errorInfo[1] ?? 0) === 1062) {
                    $errores['beca'] = 'Ya enviaste una solicitud a esta beca.';
                } else {
                    error_log('bec solicitar.php: ' . $ex->getMessage());
                    $errorGeneral = 'No pudimos registrar tu solicitud. Vuelve a intentarlo; si continúa, avisa al docente.';
                }
            }
        }

        // Si algo falló, no se dejan archivos huérfanos en uploads/
        if ($folio === '') {
            foreach ($guardados as $g) {
                @unlink(__DIR__ . '/uploads/' . $g['nombre_guardado']);
            }
        } else {
            // Envío correcto: POST-Redirect-GET
            $_SESSION['bec_envio'] = [
                'folio'    => $folio,
                'programa' => (string)$programa['nombre'],
            ];
            redirigir('solicitar.php?id=' . $programaId);
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
    <?php if ($idBeca > 0): ?>
      <li><a href="ficha.php?id=<?= (int)$idBeca ?>">Detalle</a></li>
    <?php endif; ?>
    <li aria-current="page">Solicitar</li>
  </ol>

  <?php /* Confirmación */ ?>
  <?php if ($enviado): ?>

    <div class="alerta alerta--exito" role="status">
      <div>
        <p class="alerta__titulo">¡Solicitud enviada!</p>
        <p>
          Recibimos tu postulación a <strong><?= e($nombrePrograma) ?></strong>.
          Tu folio es <strong><?= e($folio) ?></strong>; guárdalo para consultar el estado de tu solicitud.
          Te contactaremos a <strong><?= e($correoCuenta) ?></strong>.
        </p>
      </div>
    </div>

    <p>
      <a class="boton boton--primario" href="seguimiento.php">Consultar mi solicitud</a>
      <a class="boton boton--secundario" href="index.php">Volver al catálogo</a>
    </p>

  <?php elseif ($programas === []): ?>

    <div class="alerta alerta--info" role="status">
      <div>
        <p class="alerta__titulo">Sin convocatorias abiertas</p>
        <p>Por ahora no hay becas con convocatoria abierta. Revisa el catálogo más adelante.</p>
      </div>
    </div>
    <p><a class="boton boton--secundario" href="index.php">Volver al catálogo</a></p>

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
    <form class="formulario bec-formulario" method="POST" action="solicitar.php?id=<?= (int)$idBeca ?>" enctype="multipart/form-data" novalidate>
      <?= campo_token() ?>

      <fieldset class="grupo-campos">
        <legend class="grupo-campos__titulo">1. Beca a la que postulas</legend>

        <div class="<?= bec_clase_campo($errores, 'beca') ?>">
          <label class="campo__etiqueta" for="beca">Beca o apoyo <span class="campo__requerido">*</span></label>
          <select class="campo__control" id="beca" name="beca"<?= bec_aria($errores, 'beca') ?> required>
            <?php foreach ($programas as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $datos['beca'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php bec_error($errores, 'beca'); ?>
        </div>
      </fieldset>

      <fieldset class="grupo-campos">
        <legend class="grupo-campos__titulo">2. Datos del alumno</legend>

        <div class="campo">
          <label class="campo__etiqueta" for="nombre">Nombre completo</label>
          <input class="campo__control" type="text" id="nombre" value="<?= e($nombreCuenta) ?>" autocomplete="name" readonly>
          <span class="campo__ayuda">Tomado de tu cuenta.</span>
        </div>

        <div class="bec-fila">
          <div class="<?= bec_clase_campo($errores, 'matricula') ?>">
            <label class="campo__etiqueta" for="matricula">Matrícula <span class="campo__requerido">*</span></label>
            <input class="campo__control" type="text" id="matricula" name="matricula"<?= bec_aria($errores, 'matricula') ?> placeholder="Ej. TI-2201044" value="<?= e($datos['matricula']) ?>" maxlength="20" required>
            <?php bec_error($errores, 'matricula'); ?>
          </div>

          <div class="<?= bec_clase_campo($errores, 'telefono') ?>">
            <label class="campo__etiqueta" for="telefono">Teléfono</label>
            <input class="campo__control" type="tel" id="telefono" name="telefono"<?= bec_aria($errores, 'telefono') ?> value="<?= e($datos['telefono']) ?>" autocomplete="tel">
            <?php bec_error($errores, 'telefono'); ?>
          </div>
        </div>

        <div class="campo">
          <label class="campo__etiqueta" for="correo">Correo electrónico</label>
          <input class="campo__control" type="email" id="correo" value="<?= e($correoCuenta) ?>" autocomplete="email" readonly>
          <span class="campo__ayuda">Tomado de tu cuenta; con este correo y tu folio consultas el estado.</span>
        </div>

        <div class="bec-fila">
          <div class="<?= bec_clase_campo($errores, 'carrera') ?>">
            <label class="campo__etiqueta" for="carrera">Carrera <span class="campo__requerido">*</span></label>
            <select class="campo__control" id="carrera" name="carrera"<?= bec_aria($errores, 'carrera') ?> required>
              <option value="">Selecciona…</option>
              <?php foreach ($carreras as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $datos['carrera'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
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
      <a class="boton boton--secundario" href="ficha.php?id=<?= (int)$idBeca ?>">Cancelar</a>
    </form>

  <?php endif; ?>
</div>

<?php
pie();
