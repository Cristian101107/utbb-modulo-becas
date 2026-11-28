-- =============================================================================
-- MÓDULO BEC · esquema.sql
-- Explorador Vocacional UTBB
--
-- Se ejecuta DESPUÉS de sql/01-nucleo.sql.
-- =============================================================================

USE explorador_vocacional;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Limpieza previa en orden de dependencias para permitir reimportación limpia
DROP TABLE IF EXISTS `bec_dictamenes`;
DROP TABLE IF EXISTS `bec_documentos`;
DROP TABLE IF EXISTS `bec_solicitudes`;
DROP TABLE IF EXISTS `bec_requisitos`;
DROP TABLE IF EXISTS `bec_programas`;

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `bec_programas`
-- --------------------------------------------------------

CREATE TABLE `bec_programas` (
  `id` int(10) UNSIGNED NOT NULL,
  `carrera_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre` varchar(160) NOT NULL,
  `tipo` enum('academica','socioeconomica','deportiva','alimenticia','federal') NOT NULL DEFAULT 'academica',
  `descripcion` text NOT NULL,
  `monto_apoyo` varchar(120) NOT NULL,
  `promedio_minimo` decimal(3,1) NOT NULL DEFAULT 8.0,
  `fecha_inicio` date NOT NULL,
  `fecha_cierre` date NOT NULL,
  `cupo` int(10) UNSIGNED DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `estado` enum('activa','proxima','borrador','cerrada') NOT NULL DEFAULT 'activa',
  `requiere_documentos` tinyint(1) NOT NULL DEFAULT 1,
  `documentos_requeridos` text DEFAULT NULL,
  `creado_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bec_programas` (`id`, `carrera_id`, `nombre`, `tipo`, `descripcion`, `monto_apoyo`, `promedio_minimo`, `fecha_inicio`, `fecha_cierre`, `cupo`, `activa`, `estado`, `requiere_documentos`, `documentos_requeridos`, `creado_por`) VALUES
(1, NULL, 'Beca Académica', 'academica', 'Otorgada a estudiantes regulares con buen aprovechamiento escolar mediante convocatoria cuatrimestral.', 'Descuento en colegiatura · Desde 2do. cuatrimestre', 9.0, '2026-09-01', '2026-12-15', 100, 1, 'activa', 1, '["identificacion","kardex"]', 1),
(2, NULL, 'Beca Socioeconómica', 'socioeconomica', 'Dirigida a alumnos regulares que se encuentran en una condición socioeconómica vulnerable.', 'Descuento en colegiatura · Desde 2do. cuatrimestre', 8.0, '2026-09-01', '2026-12-15', 80, 1, 'activa', 1, '["identificacion","kardex","comprobante_ingreso","estudio_socio"]', 1),
(3, NULL, 'Apoyo Extracurricular', 'deportiva', 'Para alumnos que hayan participado y sobresalido en actividades extracurriculares individuales o de conjunto.', 'Descuento en colegiatura · Desde 2do. cuatrimestre', 8.0, '2026-09-01', '2026-12-15', 50, 1, 'activa', 1, '["identificacion","kardex","constancia_actividad"]', 1),
(4, NULL, 'Beca Alimenticia', 'alimenticia', 'Apoyo en el que se brinda un alimento y bebida diaria en la cafetería de la propia Universidad.', 'En especie · Cafetería Universitaria', 8.0, '2026-09-01', '2026-12-15', 120, 1, 'activa', 1, '["identificacion","comprobante_ingreso"]', 1),
(5, NULL, 'Jóvenes Escribiendo el Futuro', 'federal', 'Programa federal que busca garantizar una educación inclusiva, equitativa y de calidad para estudiantes universitarios.', 'Becas Benito Juárez · Educación Superior', 8.0, '2026-09-01', '2026-12-15', NULL, 1, 'activa', 1, '["identificacion","curp"]', 1);

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `bec_requisitos`
-- --------------------------------------------------------

CREATE TABLE `bec_requisitos` (
  `id` int(10) UNSIGNED NOT NULL,
  `programa_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(160) NOT NULL DEFAULT '',
  `descripcion` varchar(300) DEFAULT NULL,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 1,
  `tipo_archivo` enum('pdf','imagen','ambos','ninguno') NOT NULL DEFAULT 'pdf',
  `orden` tinyint(3) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bec_requisitos` (`id`, `programa_id`, `nombre`, `descripcion`, `obligatorio`, `tipo_archivo`, `orden`) VALUES
(1, 1, 'Estudiante regular (2do. cuatrimestre)', 'Ser estudiante regular inscrito a partir de 2do. cuatrimestre.', 1, 'pdf', 1),
(2, 1, 'Promedio mínimo de 9.0', 'Contar con promedio mínimo de 9.0 sin materias reprobadas ni extraordinarios (Kardex oficial).', 1, 'pdf', 2),
(3, 1, 'Sin otro apoyo vigente', 'No contar con otro apoyo o beca institucional vigente.', 1, 'ninguno', 3),
(4, 1, 'Registro en fechas de convocatoria', 'Realizar el registro dentro de las fechas establecidas en la convocatoria.', 1, 'ninguno', 4),
(5, 2, 'Estudiante regular (2do. cuatrimestre)', 'Ser estudiante regular a partir de 2do. cuatrimestre.', 1, 'pdf', 1),
(6, 2, 'Promedio mínimo de 8.0', 'Promedio mínimo de 8.0 en el cuatrimestre anterior.', 1, 'pdf', 2),
(7, 2, 'Comprobante de ingresos y estudio socioeconómico', 'Entregar comprobante de ingresos familiares y formato de estudio socioeconómico.', 1, 'pdf', 3),
(8, 2, 'Sin adeudos institucionales', 'No tener adeudos administrativos o de biblioteca con la institución.', 1, 'ninguno', 4),
(9, 3, 'Estudiante regular (2do. cuatrimestre)', 'Ser estudiante regular a partir de 2do. cuatrimestre.', 1, 'pdf', 1),
(10, 3, 'Participación extracurricular sobresaliente', 'Haber participado y sobresalido en actividades deportivas, culturales o cívicas representativas.', 1, 'ambos', 2),
(11, 3, 'Constancia o aval institucional', 'Contar con constancia o aval del área de Actividades Culturales y Deportivas.', 1, 'pdf', 3),
(12, 3, 'Promedio mínimo de 8.0', 'Mantener promedio mínimo de 8.0 en el cuatrimestre inmediato anterior.', 1, 'pdf', 4),
(13, 4, 'Estudiante regular (2do. cuatrimestre)', 'Estar inscrito como alumno regular a partir de 2do. cuatrimestre.', 1, 'pdf', 1),
(14, 4, 'Solicitud oficial de necesidad económica', 'Acreditar necesidad económica mediante solicitud oficial.', 1, 'pdf', 2),
(15, 4, 'Asistencia regular a clases', 'Mantener asistencia regular a clases durante el cuatrimestre vigente.', 1, 'ninguno', 3),
(16, 5, 'Matrícula y ficha escolar SUBES', 'Tener matrícula activa y ficha escolar activada en el portal SUBES.', 1, 'pdf', 1),
(17, 5, 'Criterios de priorización federal', 'Cumplir con los criterios de priorización publicados en la convocatoria federal vigente.', 1, 'ninguno', 2),
(18, 5, 'Cuenta bancaria CLABE activa', 'Registrar una cuenta bancaria CLABE activa a nombre del beneficiario.', 1, 'pdf', 3);

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `bec_solicitudes`
-- --------------------------------------------------------

CREATE TABLE `bec_solicitudes` (
  `id` int(10) UNSIGNED NOT NULL,
  `folio` varchar(30) NOT NULL,
  `programa_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `carrera_id` int(10) UNSIGNED NOT NULL,
  `matricula` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `cuatrimestre` tinyint(3) UNSIGNED DEFAULT NULL,
  `promedio_actual` decimal(3,1) NOT NULL,
  `motivo` text NOT NULL,
  `estado` enum('borrador','enviada','en_revision','aprobada','rechazada') NOT NULL DEFAULT 'borrador',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `enviado_en` datetime DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `bec_documentos`
-- --------------------------------------------------------

CREATE TABLE `bec_documentos` (
  `id` int(10) UNSIGNED NOT NULL,
  `solicitud_id` int(10) UNSIGNED NOT NULL,
  `tipo_documento` varchar(60) NOT NULL,
  `nombre_guardado` varchar(120) NOT NULL,
  `tipo_mime` varchar(80) NOT NULL,
  `tamano_bytes` int(10) UNSIGNED NOT NULL,
  `subido_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `bec_dictamenes`
-- --------------------------------------------------------

CREATE TABLE `bec_dictamenes` (
  `id` int(10) UNSIGNED NOT NULL,
  `solicitud_id` int(10) UNSIGNED NOT NULL,
  `revisor_id` int(10) UNSIGNED DEFAULT NULL,
  `estado_previo` varchar(20) DEFAULT NULL,
  `estado_nuevo` enum('enviada','en_revision','aprobada','rechazada') NOT NULL,
  `observaciones` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Índices para tablas volcadas
-- --------------------------------------------------------

ALTER TABLE `bec_dictamenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_bec_dictamenes_solicitud` (`solicitud_id`),
  ADD KEY `fk_bec_dictamenes_revisor` (`revisor_id`);

ALTER TABLE `bec_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_bec_documentos_solicitud` (`solicitud_id`);

ALTER TABLE `bec_programas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_bec_programas_activa` (`activa`),
  ADD KEY `ix_bec_programas_estado` (`estado`),
  ADD KEY `ix_bec_programas_tipo` (`tipo`),
  ADD KEY `ix_bec_programas_carrera` (`carrera_id`),
  ADD KEY `fk_bec_programas_usuario` (`creado_por`);

ALTER TABLE `bec_requisitos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_bec_requisitos_programa` (`programa_id`);

ALTER TABLE `bec_solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bec_solicitudes_folio` (`folio`),
  ADD UNIQUE KEY `uq_bec_usuario_programa` (`usuario_id`,`programa_id`),
  ADD KEY `ix_bec_solicitudes_estado` (`estado`),
  ADD KEY `ix_bec_solicitudes_programa` (`programa_id`),
  ADD KEY `fk_bec_solicitudes_carrera` (`carrera_id`);

-- --------------------------------------------------------
-- AUTO_INCREMENT de las tablas volcadas
-- --------------------------------------------------------

ALTER TABLE `bec_dictamenes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `bec_documentos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `bec_programas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `bec_requisitos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

ALTER TABLE `bec_solicitudes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Restricciones para tablas volcadas
-- --------------------------------------------------------

ALTER TABLE `bec_dictamenes`
  ADD CONSTRAINT `fk_bec_dictamenes_revisor` FOREIGN KEY (`revisor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bec_dictamenes_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `bec_solicitudes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `bec_documentos`
  ADD CONSTRAINT `fk_bec_documentos_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `bec_solicitudes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `bec_programas`
  ADD CONSTRAINT `fk_bec_programas_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bec_programas_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `bec_requisitos`
  ADD CONSTRAINT `fk_bec_requisitos_programa` FOREIGN KEY (`programa_id`) REFERENCES `bec_programas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `bec_solicitudes`
  ADD CONSTRAINT `fk_bec_solicitudes_carrera` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bec_solicitudes_programa` FOREIGN KEY (`programa_id`) REFERENCES `bec_programas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bec_solicitudes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;