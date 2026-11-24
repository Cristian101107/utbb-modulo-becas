<?php
/**
 * MÓDULO BECAS · Capa de consultas PDO
 * Explorador Vocacional UTBB
 */

declare(strict_types=1);

const BEC_ESTADOS = [
    'borrador' => 'Borrador',
    'proxima'  => 'Próxima',
    'activa'   => 'Activa',
    'cerrada'  => 'Cerrada',
];

function bec_clase_estado(string $estado): string
{
    return 'beca-estado beca-estado--' . $estado;
}

/**
 * Detecta el nombre real de la tabla de convocatorias en la base de datos.
 */
function bec_tabla(): string
{
    static $tabla = null;
    if ($tabla !== null) {
        return $tabla;
    }

    $pdo = bd();
    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];

    foreach ($tablas as $t) {
        $nombre = strtolower((string) $t);
        if (str_contains($nombre, 'convocatoria') || $nombre === 'bec_becas' || $nombre === 'becas') {
            $tabla = (string) $t;
            return $tabla;
        }
    }

    // Nombre por defecto siguiendo la regla de prefijo bec_ del README
    $tabla = 'bec_convocatorias';
    return $tabla;
}

function bec_listar(string $busqueda = '', string $estado = '', ?int $carreraId = null, int $pagina = 1, int $porPagina = 20): array
{
    $pdo   = bd();
    $tabla = bec_tabla();
    $where = [];
    $params = [];

    if ($busqueda !== '') {
        $where[]  = '(b.titulo LIKE ? OR c.nombre LIKE ?)';
        $like     = '%' . $busqueda . '%';
        $params[] = $like;
        $params[] = $like;
    }

    if ($estado !== '') {
        $where[]  = 'b.estado = ?';
        $params[] = $estado;
    }

    if ($carreraId !== null && $carreraId > 0) {
        $where[]  = 'b.carrera_id = ?';
        $params[] = $carreraId;
    }

    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM `{$tabla}` b LEFT JOIN carreras c ON c.id = b.carrera_id {$sqlWhere}");
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();

    $offset = max(0, ($pagina - 1) * $porPagina);
    $sql = "SELECT b.*, COALESCE(c.nombre, 'General') AS carrera
            FROM `{$tabla}` b
            LEFT JOIN carreras c ON c.id = b.carrera_id
            {$sqlWhere}
            ORDER BY b.id DESC
            LIMIT {$porPagina} OFFSET {$offset}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'filas' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'total' => $total,
    ];
}

function bec_obtener(int $id): ?array
{
    $pdo   = bd();
    $tabla = bec_tabla();
    $stmt  = $pdo->prepare("SELECT * FROM `{$tabla}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    return $fila !== false ? $fila : null;
}

function bec_crear(array $datos, int $usuarioId): int
{
    $pdo   = bd();
    $tabla = bec_tabla();

    $sql = "INSERT INTO `{$tabla}`
            (carrera_id, titulo, tipo, monto, apertura, cierre, estado, resumen, requisitos, cuerpo, imagen, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $datos['carrera_id'],
        $datos['titulo'],
        $datos['tipo'],
        $datos['monto'],
        $datos['apertura'],
        $datos['cierre'],
        $datos['estado'],
        $datos['resumen'] ?? '',
        $datos['requisitos'],
        $datos['cuerpo'] ?? '',
        $datos['imagen'] ?? null,
        $usuarioId,
    ]);

    return (int) $pdo->lastInsertId();
}

function bec_actualizar(int $id, array $datos): bool
{
    $pdo   = bd();
    $tabla = bec_tabla();

    $sql = "UPDATE `{$tabla}`
            SET carrera_id = ?, titulo = ?, tipo = ?, monto = ?, apertura = ?, cierre = ?,
                estado = ?, resumen = ?, requisitos = ?, cuerpo = ?, imagen = ?
            WHERE id = ? LIMIT 1";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $datos['carrera_id'],
        $datos['titulo'],
        $datos['tipo'],
        $datos['monto'],
        $datos['apertura'],
        $datos['cierre'],
        $datos['estado'],
        $datos['resumen'] ?? '',
        $datos['requisitos'],
        $datos['cuerpo'] ?? '',
        $datos['imagen'] ?? null,
        $id,
    ]);
}

function bec_eliminar(int $id): bool
{
    $pdo   = bd();
    $tabla = bec_tabla();
    $stmt  = $pdo->prepare("DELETE FROM `{$tabla}` WHERE id = ? LIMIT 1");
    return $stmt->execute([$id]);
}

function bec_historial(int $id): array
{
    return [];
}