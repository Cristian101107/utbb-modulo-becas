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