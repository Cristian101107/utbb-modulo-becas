<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';

cabecera('Catálogo de Becas y Apoyos', '', 'css/modulo.css');
?>

<section class="portada bec-portada">
  <div class="contenedor">
    <span class="insignia insignia--info mb-4">Convocatorias UTBB</span>
    <h1>Becas y Apoyos Estudiantiles</h1>
    <p class="entradilla">Programas institucionales disponibles a partir de 2do. cuatrimestre como descuento en colegiatura y apoyos federales vigentes.</p>
  </div>
</section>

<section class="contenedor seccion">
  <div class="disposicion-filtros">

    <!-- Panel lateral de filtros -->
    <form class="panel" method="GET" action="index.php">
      <h2>Filtrar becas</h2>

      <div class="campo">
        <label class="campo__etiqueta" for="q">Buscar</label>
        <input class="campo__control" type="search" id="q" name="q" placeholder="Ej. Académica, Alimenticia...">
      </div>

      <div class="campo">
        <label class="campo__etiqueta" for="tipo">Tipo de beca</label>
        <select class="campo__control" id="tipo" name="tipo">
          <option value="">Todos los tipos</option>
          <option value="academica">Académica</option>
          <option value="socioeconomica">Socioeconómica</option>
          <option value="extracurricular">Apoyo Extracurricular</option>
          <option value="alimenticia">Alimenticia</option>
          <option value="federal">Federal (Benito Juárez)</option>
        </select>
      </div>

      <div class="campo">
        <label class="campo__etiqueta" for="modalidad">Modalidad de apoyo</label>
        <select class="campo__control" id="modalidad" name="modalidad">
          <option value="">Todas</option>
          <option value="colegiatura">Descuento en colegiatura</option>
          <option value="especie">En especie (Cafetería)</option>
          <option value="economico">Estímulo federal</option>
        </select>
      </div>

      <button class="boton boton--primario boton--bloque" type="submit">Aplicar filtros</button>
    </form>

    <!-- Rejilla de tarjetas de becas -->
    <div>
      <p class="leyenda">Mostrando 5 programas de apoyo disponibles</p>

      <div class="rejilla">
        <!-- Tarjeta 1 -->
        <a class="tarjeta bec-tarjeta" href="ficha.php">
          <div class="tarjeta__cuerpo">
            <span class="bec-etiqueta-tipo">Beca Institucional</span>
            <h3 class="tarjeta__titulo">Beca Académica</h3>
            <p class="leyenda">Descuento en colegiatura · Desde 2do. cuatrimestre</p>
            <p>Otorgada a estudiantes regulares con buen aprovechamiento escolar mediante convocatoria cuatrimestral.</p>
          </div>
          <div class="tarjeta__pie">
            <span class="insignia insignia--publicado">Abierta</span>
            <span>Ver detalles</span>
          </div>
        </a>

        <!-- Tarjeta 2 -->
        <a class="tarjeta bec-tarjeta" href="ficha.php">
          <div class="tarjeta__cuerpo">
            <span class="bec-etiqueta-tipo">Beca Institucional</span>
            <h3 class="tarjeta__titulo">Beca Socioeconómica</h3>
            <p class="leyenda">Descuento en colegiatura · Desde 2do. cuatrimestre</p>
            <p>Dirigida a alumnos regulares que se encuentran en una condición socioeconómica vulnerable.</p>
          </div>
          <div class="tarjeta__pie">
            <span class="insignia insignia--publicado">Abierta</span>
            <span>Ver detalles</span>
          </div>
        </a>

        <!-- Tarjeta 3 -->
        <a class="tarjeta bec-tarjeta" href="ficha.php">
          <div class="tarjeta__cuerpo">
            <span class="bec-etiqueta-tipo">Beca Institucional</span>
            <h3 class="tarjeta__titulo">Apoyo Extracurricular</h3>
            <p class="leyenda">Descuento en colegiatura · Desde 2do. cuatrimestre</p>
            <p>Para alumnos que hayan participado y sobresalido en actividades extracurriculares individuales o de conjunto.</p>
          </div>
          <div class="tarjeta__pie">
            <span class="insignia insignia--publicado">Abierta</span>
            <span>Ver detalles</span>
          </div>
        </a>

        <!-- Tarjeta 4 -->
        <a class="tarjeta bec-tarjeta" href="ficha.php">
          <div class="tarjeta__cuerpo">
            <span class="bec-etiqueta-tipo">Beca Institucional</span>
            <h3 class="tarjeta__titulo">Beca Alimenticia</h3>
            <p class="leyenda">En especie · Cafetería Universitaria</p>
            <p>Apoyo en el que se brinda un alimento y bebida diaria en la cafetería de la propia Universidad.</p>
          </div>
          <div class="tarjeta__pie">
            <span class="insignia insignia--publicado">Abierta</span>
            <span>Ver detalles</span>
          </div>
        </a>

        <!-- Tarjeta 5 -->
        <a class="tarjeta bec-tarjeta" href="ficha.php">
          <div class="tarjeta__cuerpo">
            <span class="bec-etiqueta-tipo">Programa Federal</span>
            <h3 class="tarjeta__titulo">Jóvenes Escribiendo el Futuro</h3>
            <p class="leyenda">Becas Benito Juárez · Educación Superior</p>
            <p>Programa federal que busca garantizar una educación inclusiva, equitativa y de calidad para estudiantes universitarios.</p>
          </div>
          <div class="tarjeta__pie">
            <span class="insignia insignia--info">Federal</span>
            <span>Ver detalles</span>
          </div>
        </a>
      </div>

      <!-- Información de contacto institucional -->
      <div class="bec-contacto">
        <span><strong>Atención:</strong> Edificio de Biblioteca</span>
        <span><strong>Teléfono:</strong> (+52) 322 226 8300 Ext. 1502</span>
        <span><strong>Correo:</strong> becas@utbb.edu.mx</span>
      </div>
    </div>

  </div>
</section>

<?php
pie();