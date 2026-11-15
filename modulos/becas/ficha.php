<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';

$becas = [
    'academica' => [
        'tipo'         => 'Beca Institucional',
        'titulo'       => 'Beca Académica',
        'estado'       => 'Abierta',
        'clase_estado' => 'insignia--publicado',
        'beneficio'    => 'Descuento en el pago de colegiatura cuatrimestral',
        'dirigida'     => 'Estudiantes regulares a partir de 2do. cuatrimestre',
        'descripcion'  => 'Otorgada a estudiantes regulares con buen aprovechamiento escolar mediante convocatoria cuatrimestral.',
        'requisitos'   => [
            'Ser estudiante regular inscrito a partir de 2do. cuatrimestre.',
            'Contar con promedio mínimo de 9.0 sin materias reprobadas ni extraordinarios.',
            'No contar con otro apoyo o beca institucional vigente.',
            'Realizar el registro dentro de las fechas establecidas en la convocatoria.'
        ]
    ],
    'socioeconomica' => [
        'tipo'         => 'Beca Institucional',
        'titulo'       => 'Beca Socioeconómica',
        'estado'       => 'Abierta',
        'clase_estado' => 'insignia--publicado',
        'beneficio'    => 'Descuento en colegiatura · Desde 2do. cuatrimestre',
        'dirigida'     => 'Estudiantes regulares a partir de 2do. cuatrimestre',
        'descripcion'  => 'Dirigida a alumnos regulares que se encuentran en una condición socioeconómica vulnerable.',
        'requisitos'   => [
            'Ser estudiante regular a partir de 2do. cuatrimestre.',
            'Promedio mínimo de 8.0 en el cuatrimestre anterior.',
            'Entregar comprobante de ingresos familiares y formato de estudio socioeconómico.',
            'No tener adeudos administrativos o de biblioteca con la institución.'
        ]
    ],
    'extracurricular' => [
        'tipo'         => 'Beca Institucional',
        'titulo'       => 'Apoyo Extracurricular',
        'estado'       => 'Abierta',
        'clase_estado' => 'insignia--publicado',
        'beneficio'    => 'Descuento en colegiatura · Desde 2do. cuatrimestre',
        'dirigida'     => 'Alumnos participantes en actividades extracurriculares UTBB',
        'descripcion'  => 'Para alumnos que hayan participado y sobresalido en actividades extracurriculares individuales o de conjunto.',
        'requisitos'   => [
            'Ser estudiante regular a partir de 2do. cuatrimestre.',
            'Haber participado y sobresalido en actividades deportivas, culturales o cívicas representativas.',
            'Contar con constancia o aval del área de Actividades Culturales y Deportivas.',
            'Mantener promedio mínimo de 8.0 en el cuatrimestre inmediato anterior.'
        ]
    ],
    'alimenticia' => [
        'tipo'         => 'Beca Institucional',
        'titulo'       => 'Beca Alimenticia',
        'estado'       => 'Abierta',
        'clase_estado' => 'insignia--publicado',
        'beneficio'    => 'En especie · Cafetería Universitaria (un alimento y bebida diaria)',
        'dirigida'     => 'Estudiantes regulares a partir de 2do. cuatrimestre',
        'descripcion'  => 'Apoyo en el que se brinda un alimento y bebida diaria en la cafetería de la propia Universidad.',
        'requisitos'   => [
            'Estar inscrito como alumno regular a partir de 2do. cuatrimestre.',
            'Acreditar necesidad económica mediante solicitud oficial.',
            'Mantener asistencia regular a clases durante el cuatrimestre vigente.'
        ]
    ],
    'federal' => [
        'tipo'         => 'Programa Federal',
        'titulo'       => 'Jóvenes Escribiendo el Futuro',
        'estado'       => 'Federal',
        'clase_estado' => 'insignia--info',
        'beneficio'    => 'Estímulo económico federal (Becas Benito Juárez · Educación Superior)',
        'dirigida'     => 'Estudiantes universitarios inscritos conforme a reglas de operación federales',
        'descripcion'  => 'Programa federal que busca garantizar una educación inclusiva, equitativa y de calidad para estudiantes universitarios.',
        'requisitos'   => [
            'Tener matrícula activa y ficha escolar activada en el portal SUBES.',
            'Cumplir con los criterios de priorización publicados en la convocatoria federal vigente.',
            'Registrar una cuenta bancaria CLABE activa a nombre del beneficiario.'
        ]
    ]
];

$id = get('id', 'academica');
$beca = $becas[$id] ?? null;

if ($beca === null) {
    no_encontrado('La beca solicitada no existe.');
}

cabecera($beca['titulo'] . ' — Becas UTBB', '', 'css/modulo.css');
?>

<section class="portada bec-portada">
  <div class="contenedor">
    <span class="insignia <?= e($beca['clase_estado']) ?> mb-4"><?= e($beca['estado']) ?></span>
    <h1><?= e($beca['titulo']) ?></h1>
    <p class="entradilla"><?= e($beca['descripcion']) ?></p>
  </div>
</section>

<main class="contenedor seccion">
  <?php pintar_mensajes(); ?>

  <div class="panel">
    <h2>Detalles del apoyo</h2>
    <p><strong>Modalidad:</strong> <?= e($beca['tipo']) ?></p>
    <p><strong>Beneficio:</strong> <?= e($beca['beneficio']) ?></p>
    <p><strong>Población objetivo:</strong> <?= e($beca['dirigida']) ?></p>

    <h3 class="mt-6">Requisitos principales</h3>
    <ul>
      <?php foreach ($beca['requisitos'] as $req): ?>
        <li><?= e($req) ?></li>
      <?php endforeach; ?>
    </ul>

    <div class="mt-6">
      <a class="boton boton--primario" href="solicitar.php?id=<?= e($id) ?>">Iniciar solicitud</a>
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