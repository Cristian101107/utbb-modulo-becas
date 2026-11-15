<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';

cabecera('Seguimiento de Becas', '', 'css/modulo.css');
?>

<section class="portada bec-portada">
  <div class="contenedor">
    <span class="insignia insignia--info mb-4">Convocatorias UTBB

    </span>

   <h1>Seguimiento de Becas y Apoyos Estudiantiles</h1>

    <p class="entradilla">
        Consulta el estado de tu solicitud de beca o apoyo estudiantil.
    </p>
    <a href="consulta.html" class="btn-consulta">
      Consultar solicitud
    </a>
  </div>
</section>

<form class="contenedor seccion" method="GET" action="seguimiento.php">
    <h2>Consultar estado de solicitud</h2>
  <div class="campo">
    <label class="campo__etiqueta" for="folio">Número de folio</label>
    <input class="campo__control" type="text" id="folio" name="folio" placeholder="Ej. 123456789">
    <label class="campo__etiqueta" for="correo">Correo electrónico</label>
    <input class="campo__control" type="email" id="correo" name="correo" placeholder="Ej. usuario@utbb.edu.mx">
  </div>

  <button class="boton boton--primario boton--bloque" type="submit">Consultar estado</button>
</form>

<div class="contenedor seccion">
  <h2>Resultados de la consulta</h2>
  <p class="leyenda">Mostrando resultados para el folio: <strong>123456789</strong></p>

  <div class="resultado-consulta">
    <p><strong>Estado de la solicitud:</strong> En revisión</p>
    <p><strong>Fecha de solicitud:</strong> 15 de marzo de 2024</p>
    <p><strong>Tipo de beca:</strong> Académica</p>
    <p><strong>Modalidad:</strong> Descuento en colegiatura</p>
  </div>

