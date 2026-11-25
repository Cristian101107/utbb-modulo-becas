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
