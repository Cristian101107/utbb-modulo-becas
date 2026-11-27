<?php
declare(strict_types=1);

const BEC_TIPOS = [
    'academica'      => 'Académica',
    'socioeconomica' => 'Socioeconómica',
    'deportiva'      => 'Apoyo Extracurricular',
    'cultural'       => 'Cultural',
    'transporte'     => 'Transporte',
    'alimenticia'    => 'Alimenticia',
    'federal'        => 'Programa Federal',
];

const BEC_ESTADOS_SOLICITUD = [
    'borrador'    => 'Borrador',
    'enviada'     => 'Enviada',
    'en_revision' => 'En revisión',
    'aprobada'    => 'Aprobada',
    'rechazada'   => 'Rechazada',
];

/** Lista los programas de becas activos con búsqueda y filtros. */
function bec_listar_programas(string $busqueda = '', string $tipo = '', string $modalidad = ''): array
{
    $condiciones = ['p.activa = 1'];
    $valores     = [];

    if ($busqueda !== '') {
        $condiciones[] = '(p.nombre LIKE ? OR p.descripcion LIKE ?)';
        $comodin = '%' . $busqueda . '%';
        $valores[] = $comodin;
        $valores[] = $comodin;
    }

    if ($tipo !== '' && array_key_exists($tipo, BEC_TIPOS)) {
        $condiciones[] = 'p.tipo = ?';
        $valores[]     = $tipo;
    }

    if ($modalidad !== '') {
        if ($modalidad === 'colegiatura') {
            $condiciones[] = 'p.monto_apoyo LIKE ?';
            $valores[]     = '%colegiatura%';
        } elseif ($modalidad === 'especie') {
            $condiciones[] = 'p.monto_apoyo LIKE ?';
            $valores[]     = '%especie%';
        } elseif ($modalidad === 'economico') {
            $condiciones[] = 'p.tipo = ?';
            $valores[]     = 'federal';
        }
    }

    $donde = 'WHERE ' . implode(' AND ', $condiciones);

    $sql = "SELECT p.*, c.nombre AS carrera
              FROM bec_programas p
         LEFT JOIN carreras c ON c.id = p.carrera_id
              $donde
          ORDER BY p.id ASC";

    $con = bd()->prepare($sql);
    $con->execute($valores);

    return $con->fetchAll();
}

/** Obtiene un programa por ID junto con su lista de requisitos desde bec_requisitos. */
function bec_obtener_programa(int $id): ?array
{
    $con = bd()->prepare(
        'SELECT p.*, c.nombre AS carrera
           FROM bec_programas p
      LEFT JOIN carreras c ON c.id = p.carrera_id
          WHERE p.id = ?'
    );
    $con->execute([$id]);
    $programa = $con->fetch();

    if (!$programa) {
        return null;
    }

    $conReq = bd()->prepare(
        'SELECT *
           FROM bec_requisitos
          WHERE programa_id = ?
       ORDER BY orden ASC, id ASC'
    );
    $conReq->execute([$id]);
    $programa['requisitos'] = $conReq->fetchAll();

    return $programa;
}


/** Programas con convocatoria abierta hoy: activos y dentro de sus fechas. */
function bec_programas_abiertos(): array
{
    return bd()->query(
        'SELECT id, nombre, tipo, promedio_minimo, fecha_cierre
           FROM bec_programas
          WHERE activa = 1
            AND CURDATE() BETWEEN fecha_inicio AND fecha_cierre
       ORDER BY id ASC'
    )->fetchAll();
}

/** Solicitud que un usuario ya tiene para un programa (folio y estado), o null. */
function bec_solicitud_de_usuario(int $usuarioId, int $programaId): ?array
{
    $con = bd()->prepare(
        'SELECT id, folio, estado
           FROM bec_solicitudes
          WHERE usuario_id = ? AND programa_id = ?
          LIMIT 1'
    );
    $con->execute([$usuarioId, $programaId]);

    return $con->fetch() ?: null;
}

/**
 * Registra una solicitud enviada junto con sus documentos, todo en una sola
 * transacción: o se guarda completo o no se guarda nada.
 *
 * El folio se construye con el id autoincremental de la propia solicitud
 * (BEC-AAAA-00042), así que nunca se repite aunque dos alumnos envíen al mismo
 * tiempo. Mientras se obtiene el id, la fila lleva un folio temporal.
 *
 * @param array $d    programa_id, usuario_id, carrera_id, matricula, telefono (o null),
 *                    cuatrimestre, promedio (texto "9.5"), motivo
 * @param array $docs Cada uno: tipo_documento, nombre_guardado, tipo_mime, tamano_bytes
 * @return string Folio asignado
 * @throws PDOException si algo falla (la transacción se revierte)
 */
function bec_crear_solicitud(array $d, array $docs): string
{
    $pdo = bd();
    $pdo->beginTransaction();

    try {
        $alta = $pdo->prepare(
            'INSERT INTO bec_solicitudes
                (folio, programa_id, usuario_id, carrera_id, matricula, telefono,
                 cuatrimestre, promedio_actual, motivo, estado, enviado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $alta->execute([
            'TMP-' . bin2hex(random_bytes(12)),
            $d['programa_id'],
            $d['usuario_id'],
            $d['carrera_id'],
            $d['matricula'],
            $d['telefono'],
            $d['cuatrimestre'],
            $d['promedio'],
            $d['motivo'],
            'enviada',
        ]);

        $solicitudId = (int)$pdo->lastInsertId();
        $folio       = sprintf('BEC-%s-%05d', date('Y'), $solicitudId);

        $pdo->prepare('UPDATE bec_solicitudes SET folio = ? WHERE id = ?')
            ->execute([$folio, $solicitudId]);

        $altaDoc = $pdo->prepare(
            'INSERT INTO bec_documentos
                (solicitud_id, tipo_documento, nombre_guardado, tipo_mime, tamano_bytes)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($docs as $doc) {
            $altaDoc->execute([
                $solicitudId,
                $doc['tipo_documento'],
                $doc['nombre_guardado'],
                $doc['tipo_mime'],
                $doc['tamano_bytes'],
            ]);
        }

        $pdo->commit();

        return $folio;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}


/**
 * Busca una solicitud por folio Y correo del solicitante (seguimiento público).
 * Exigir ambos datos evita que alguien adivine folios ajenos.
 * Devuelve folio, estado, fechas, programa y, si ya hay resolución, la observación del comité.
 */
function bec_consultar_seguimiento(string $folio, string $correo): ?array
{
    $con = bd()->prepare(
        'SELECT s.id, s.folio, s.estado, s.enviado_en, s.actualizado_en,
                p.nombre AS programa, p.monto_apoyo
           FROM bec_solicitudes s
           JOIN bec_programas p ON p.id = s.programa_id
           JOIN usuarios u      ON u.id = s.usuario_id
          WHERE s.folio = ? AND u.correo = ?
          LIMIT 1'
    );
    $con->execute([$folio, $correo]);
    $sol = $con->fetch();

    if (!$sol) {
        return null;
    }

    $sol['observaciones'] = null;
    if (in_array($sol['estado'], ['aprobada', 'rechazada'], true)) {
        $dic = bd()->prepare(
            'SELECT observaciones FROM bec_dictamenes
              WHERE solicitud_id = ? ORDER BY id DESC LIMIT 1'
        );
        $dic->execute([$sol['id']]);
        $sol['observaciones'] = $dic->fetchColumn() ?: null;
    }

    return $sol;
}
/**
 * Lista los programas de becas para el panel administrativo (compatibilidad con admin/index.php).
 */
function bec_listar(string $busqueda = '', string $estado = '', ?int $carreraId = null, int $pagina = 1): array
{
    $condiciones = ['1 = 1'];
    $valores     = [];

    if ($busqueda !== '') {
        $condiciones[] = '(p.nombre LIKE ? OR c.nombre LIKE ?)';
        $comodin       = '%' . $busqueda . '%';
        $valores[]     = $comodin;
        $valores[]     = $comodin;
    }

    if ($carreraId !== null && $carreraId > 0) {
        $condiciones[] = '(p.carrera_id = ? OR p.carrera_id IS NULL)';
        $valores[]     = $carreraId;
    }

    if ($estado !== '') {
        if ($estado === 'borrador') {
            $condiciones[] = 'p.activa = 0';
        } elseif ($estado === 'proxima') {
            $condiciones[] = '(p.activa = 1 AND CURDATE() < p.fecha_inicio)';
        } elseif ($estado === 'activa') {
            $condiciones[] = '(p.activa = 1 AND CURDATE() BETWEEN p.fecha_inicio AND p.fecha_cierre)';
        } elseif ($estado === 'cerrada') {
            $condiciones[] = '(p.activa = 1 AND CURDATE() > p.fecha_cierre)';
        }
    }

    $donde = 'WHERE ' . implode(' AND ', $condiciones);

    // Total de registros para el caption de la tabla
    $conTotal = bd()->prepare(
        "SELECT COUNT(*)
           FROM bec_programas p
      LEFT JOIN carreras c ON c.id = p.carrera_id
           $donde"
    );
    $conTotal->execute($valores);
    $total = (int) $conTotal->fetchColumn();

    // Paginación (15 por página)
    $porPagina = 15;
    $offset    = max(0, ($pagina - 1) * $porPagina);

    $sql = "SELECT p.id,
                   p.nombre AS titulo,
                   c.nombre AS carrera,
                   p.monto_apoyo AS monto,
                   p.fecha_inicio AS apertura,
                   p.fecha_cierre AS cierre,
                   p.actualizado_en,
                   CASE
                     WHEN p.activa = 0 THEN 'borrador'
                     WHEN CURDATE() < p.fecha_inicio THEN 'proxima'
                     WHEN CURDATE() > p.fecha_cierre THEN 'cerrada'
                     ELSE 'activa'
                   END AS estado
              FROM bec_programas p
         LEFT JOIN carreras c ON c.id = p.carrera_id
              $donde
          ORDER BY p.id DESC
             LIMIT $porPagina OFFSET $offset";

    $con = bd()->prepare($sql);
    $con->execute($valores);

    return [
        'filas' => $con->fetchAll(),
        'total' => $total,
    ];
}
/**
 * Convierte las categorías en texto del formulario admin al ENUM de bec_programas.
 */
function bec_mapear_tipo_bd(string $tipoForm): string
{
    $mapa = [
        'Mérito Académico'     => 'academica',
        'Socioeconómica'       => 'socioeconomica',
        'Movilidad'            => 'federal',
        'Investigación'        => 'academica',
        'Deportiva / Cultural' => 'deportiva',
    ];

    return $mapa[$tipoForm] ?? (array_key_exists($tipoForm, BEC_TIPOS) ? $tipoForm : 'academica');
}

/**
 * Convierte el ENUM de bec_programas a la etiqueta del formulario admin.
 */
function bec_mapear_tipo_form(string $tipoBd): string
{
    $mapa = [
        'academica'      => 'Mérito Académico',
        'socioeconomica' => 'Socioeconómica',
        'deportiva'      => 'Deportiva / Cultural',
        'cultural'       => 'Deportiva / Cultural',
        'transporte'     => 'Socioeconómica',
        'alimenticia'    => 'Socioeconómica',
        'federal'        => 'Movilidad',
    ];

    return $mapa[$tipoBd] ?? 'Mérito Académico';
}

/**
 * Obtiene una convocatoria para editar o eliminar en el panel administrativo.
 */
function bec_obtener(int $id): ?array
{
    $con = bd()->prepare('SELECT * FROM bec_programas WHERE id = ? LIMIT 1');
    $con->execute([$id]);
    $p = $con->fetch();

    if (!$p) {
        return null;
    }

    // Trae los requisitos de bec_requisitos y los une línea por línea para el textarea
    $conReq = bd()->prepare('SELECT descripcion FROM bec_requisitos WHERE programa_id = ? ORDER BY orden ASC, id ASC');
    $conReq->execute([$id]);
    $listaReq = $conReq->fetchAll(PDO::FETCH_COLUMN);

    $hoy = date('Y-m-d');
    if ((int) $p['activa'] === 0) {
        $estado = 'borrador';
    } elseif ($hoy < $p['fecha_inicio']) {
        $estado = 'proxima';
    } elseif ($hoy > $p['fecha_cierre']) {
        $estado = 'cerrada';
    } else {
        $estado = 'activa';
    }

    return [
        'id'         => (int) $p['id'],
        'carrera_id' => $p['carrera_id'] !== null ? (int) $p['carrera_id'] : null,
        'titulo'     => $p['nombre'],
        'tipo'       => bec_mapear_tipo_form((string) $p['tipo']),
        'monto'      => $p['monto_apoyo'],
        'apertura'   => $p['fecha_inicio'],
        'cierre'     => $p['fecha_cierre'],
        'estado'     => $estado,
        'resumen'    => $p['descripcion'],
        'requisitos' => implode("\n", $listaReq),
        'cuerpo'     => $p['descripcion'],
        'imagen'     => null,
    ];
}

/**
 * Crea una nueva convocatoria y guarda cada línea de requisitos en bec_requisitos.
 */
function bec_crear(array $datos, int $usuarioId): int
{
    $pdo = bd();
    $pdo->beginTransaction();

    try {
        $activa      = ($datos['estado'] === 'borrador') ? 0 : 1;
        $descripcion = trim((string) ($datos['resumen'] ?: $datos['cuerpo']));
        if ($descripcion === '') {
            $descripcion = $datos['titulo'];
        }

        $ins = $pdo->prepare(
            'INSERT INTO bec_programas
                (carrera_id, nombre, tipo, descripcion, monto_apoyo, promedio_minimo, fecha_inicio, fecha_cierre, activa)
             VALUES (?, ?, ?, ?, ?, 8.00, ?, ?, ?)'
        );
        $ins->execute([
            $datos['carrera_id'],
            $datos['titulo'],
            bec_mapear_tipo_bd((string) $datos['tipo']),
            $descripcion,
            $datos['monto'],
            $datos['apertura'],
            $datos['cierre'],
            $activa,
        ]);

        $programaId = (int) $pdo->lastInsertId();

        $lineas = preg_split('/\r\n|\r|\n/', (string) ($datos['requisitos'] ?? '')) ?: [];
        $insReq = $pdo->prepare(
            'INSERT INTO bec_requisitos (programa_id, descripcion, obligatorio, orden) VALUES (?, ?, 1, ?)'
        );
        $orden = 1;
        foreach ($lineas as $linea) {
            $limpia = trim(ltrim($linea, "-•* \t"));
            if ($limpia !== '') {
                $insReq->execute([$programaId, mb_substr($limpia, 0, 255), $orden++]);
            }
        }

        $pdo->commit();
        return $programaId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Actualiza una convocatoria existente y sincroniza sus requisitos en bec_requisitos.
 */
function bec_actualizar(int $id, array $datos): void
{
    $pdo = bd();
    $pdo->beginTransaction();

    try {
        $activa      = ($datos['estado'] === 'borrador') ? 0 : 1;
        $descripcion = trim((string) ($datos['resumen'] ?: $datos['cuerpo']));
        if ($descripcion === '') {
            $descripcion = $datos['titulo'];
        }

        $upd = $pdo->prepare(
            'UPDATE bec_programas
                SET carrera_id = ?,
                    nombre = ?,
                    tipo = ?,
                    descripcion = ?,
                    monto_apoyo = ?,
                    fecha_inicio = ?,
                    fecha_cierre = ?,
                    activa = ?
              WHERE id = ?'
        );
        $upd->execute([
            $datos['carrera_id'],
            $datos['titulo'],
            bec_mapear_tipo_bd((string) $datos['tipo']),
            $descripcion,
            $datos['monto'],
            $datos['apertura'],
            $datos['cierre'],
            $activa,
            $id,
        ]);

        // Reemplaza los requisitos con los capturados en el textarea
        $pdo->prepare('DELETE FROM bec_requisitos WHERE programa_id = ?')->execute([$id]);

        $lineas = preg_split('/\r\n|\r|\n/', (string) ($datos['requisitos'] ?? '')) ?: [];
        $insReq = $pdo->prepare(
            'INSERT INTO bec_requisitos (programa_id, descripcion, obligatorio, orden) VALUES (?, ?, 1, ?)'
        );
        $orden = 1;
        foreach ($lineas as $linea) {
            $limpia = trim(ltrim($linea, "-•* \t"));
            if ($limpia !== '') {
                $insReq->execute([$id, mb_substr($limpia, 0, 255), $orden++]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Elimina una convocatoria y sus requisitos asociados (si no tiene solicitudes vinculadas).
 */
function bec_eliminar(int $id): void
{
    $pdo = bd();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('DELETE FROM bec_requisitos WHERE programa_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM bec_programas WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
/**
 * Lista las solicitudes recibidas para la bandeja del comité.
 */
/** Bandeja del comité: lista solicitudes enviadas. */
function bec_bandeja_comite(string $estado = '', ?int $carreraId = null): array
{
    $sql = "SELECT s.id, s.folio, s.promedio_actual, s.estado,
                   p.nombre AS programa, u.nombre AS alumno, c.nombre AS carrera
              FROM bec_solicitudes s
              JOIN bec_programas p ON p.id = s.programa_id
              JOIN usuarios u ON u.id = s.usuario_id
              JOIN carreras c ON c.id = s.carrera_id
             WHERE s.estado != 'borrador'";
    $params = [];

    if ($estado !== '') {
        $sql .= " AND s.estado = ?";
        $params[] = $estado;
    }
    if ($carreraId !== null && $carreraId > 0) {
        $sql .= " AND s.carrera_id = ?";
        $params[] = $carreraId;
    }

    $con = bd()->prepare($sql . " ORDER BY s.id DESC");
    $con->execute($params);
    return $con->fetchAll();
}

/** Expediente completo para revisar en el comité. */
function bec_obtener_solicitud_admin(int $id): ?array
{
    $con = bd()->prepare(
        "SELECT s.*, p.nombre AS programa, p.promedio_minimo,
                u.nombre AS alumno, u.correo, c.nombre AS carrera
           FROM bec_solicitudes s
           JOIN bec_programas p ON p.id = s.programa_id
           JOIN usuarios u ON u.id = s.usuario_id
           JOIN carreras c ON c.id = s.carrera_id
          WHERE s.id = ?"
    );
    $con->execute([$id]);
    $sol = $con->fetch();
    if (!$sol) return null;

    $docs = bd()->prepare("SELECT * FROM bec_documentos WHERE solicitud_id = ?");
    $docs->execute([$id]);
    $sol['documentos'] = $docs->fetchAll();

    $dic = bd()->prepare(
        "SELECT d.*, u.nombre AS revisor FROM bec_dictamenes d
         LEFT JOIN usuarios u ON u.id = d.revisor_id
         WHERE d.solicitud_id = ? ORDER BY d.id DESC"
    );
    $dic->execute([$id]);
    $sol['dictamenes'] = $dic->fetchAll();

    return $sol;
}

/** Guarda el dictamen y actualiza el estado. */
function bec_guardar_dictamen(int $solicitudId, int $revisorId, string $estadoPrevio, string $estadoNuevo, string $observaciones): void
{
    bd()->prepare(
        "INSERT INTO bec_dictamenes (solicitud_id, revisor_id, estado_previo, estado_nuevo, observaciones)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$solicitudId, $revisorId, $estadoPrevio, $estadoNuevo, $observaciones]);

    bd()->prepare("UPDATE bec_solicitudes SET estado = ? WHERE id = ?")
        ->execute([$estadoNuevo, $solicitudId]);
}

/** Obtiene un documento para descargarlo. */
function bec_obtener_documento(int $id): ?array
{
    $con = bd()->prepare(
        "SELECT d.*, s.carrera_id FROM bec_documentos d
         JOIN bec_solicitudes s ON s.id = d.solicitud_id WHERE d.id = ?"
    );
    $con->execute([$id]);
    return $con->fetch() ?: null;
}