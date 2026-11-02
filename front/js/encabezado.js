/*
  encabezado.js
  Partes que se repiten en todas las páginas: logos, selector de idioma y pie de página.
*/

const Encabezado = {

  // Los dos logos (empresa y SGeR) juntos, arriba a la izquierda. Llevan a la página principal.
  crearLogos: function () {
    const logoEmpresa = crearElemento('img', { src: RAIZ + 'assets/logo-empresa.svg', alt: traducir('company') });
    const separador = crearElemento('span', { clase: 'brand-sep' });
    const logoPrograma = crearElemento('img', { src: RAIZ + 'assets/logo-sger.svg', alt: 'SGeR' });

    return crearElemento('a', { clase: 'brand', href: RAIZ + 'index.html', 'aria-label': 'SGeR' },
      logoEmpresa, separador, logoPrograma);
  },

  // Lista desplegable para elegir idioma. Al cambiarlo se recarga la página.
  crearSelectorDeIdioma: function () {
    const opciones = [];
    for (const idioma of IDIOMAS) {
      opciones.push({ valor: idioma.codigo, texto: idioma.nombre });
    }

    const selector = crearSelect(opciones, idiomaActual);
    selector.className = 'lang';
    selector.setAttribute('aria-label', traducir('lang'));
    selector.addEventListener('change', function () {
      cambiarIdioma(selector.value);
      location.reload();
    });
    return selector;
  },

  // Reemplaza <div id="foot"></div> por el pie de página con los disclaimers.
  montarPie: function () {
    const lugar = document.getElementById('foot');
    if (!lugar) {
      return;
    }
    const pie = crearElemento('footer', { clase: 'footer', id: 'contacto' }, traducir('footer'));
    lugar.replaceWith(pie);
  }
};
