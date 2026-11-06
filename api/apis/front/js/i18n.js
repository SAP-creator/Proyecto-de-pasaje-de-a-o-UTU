/*
  i18n.js
  Idiomas del front. Los textos están en js/idiomas/es.js, en.js y pt.js.
  Para agregar un idioma: crear su archivo de textos, cargarlo en los HTML
  y agregarlo a IDIOMAS y TEXTOS_POR_IDIOMA.
*/

const IDIOMAS = [
  { codigo: 'es', nombre: 'Español' },
  { codigo: 'en', nombre: 'English' },
  { codigo: 'pt', nombre: 'Português' }
];

/*
  Registra los textos de cada idioma. Si un archivo de js/idiomas/ no se cargó,
  se muestra un aviso que dice cuál falta (y la página sigue funcionando con las claves a la vista).
*/
const TEXTOS_POR_IDIOMA = {};

function avisarArchivoDeIdiomaFaltante(archivo) {
  mostrarErrorFatal('No se pudo cargar ' + archivo + '. Revisá que exista la carpeta js/idiomas/ con es.js, en.js y pt.js.');
}

if (typeof TEXTOS_ES !== 'undefined') {
  TEXTOS_POR_IDIOMA.es = TEXTOS_ES;
} else {
  TEXTOS_POR_IDIOMA.es = {};
  avisarArchivoDeIdiomaFaltante('js/idiomas/es.js');
}

if (typeof TEXTOS_EN !== 'undefined') {
  TEXTOS_POR_IDIOMA.en = TEXTOS_EN;
} else {
  TEXTOS_POR_IDIOMA.en = {};
  avisarArchivoDeIdiomaFaltante('js/idiomas/en.js');
}

if (typeof TEXTOS_PT !== 'undefined') {
  TEXTOS_POR_IDIOMA.pt = TEXTOS_PT;
} else {
  TEXTOS_POR_IDIOMA.pt = {};
  avisarArchivoDeIdiomaFaltante('js/idiomas/pt.js');
}

function esIdiomaSoportado(codigo) {
  for (const idioma of IDIOMAS) {
    if (idioma.codigo === codigo) {
      return true;
    }
  }
  return false;
}

// Primero se usa el idioma que la persona eligió; si no, el del navegador; si no, español.
function elegirIdiomaInicial() {
  const idiomaGuardado = localStorage.getItem('sger_idioma');
  if (idiomaGuardado && esIdiomaSoportado(idiomaGuardado)) {
    return idiomaGuardado;
  }

  const idiomaDelNavegador = (navigator.language || 'es').substring(0, 2);
  if (esIdiomaSoportado(idiomaDelNavegador)) {
    return idiomaDelNavegador;
  }

  return 'es';
}

let idiomaActual = elegirIdiomaInicial();
document.documentElement.lang = idiomaActual;

function cambiarIdioma(codigo) {
  localStorage.setItem('sger_idioma', codigo);
  idiomaActual = codigo;
}

function existeTexto(clave) {
  return TEXTOS_POR_IDIOMA.es[clave] !== undefined;
}

// Devuelve el texto de la clave en el idioma actual. Si falta, usa español; si falta, la clave.
function traducir(clave) {
  const textosDelIdioma = TEXTOS_POR_IDIOMA[idiomaActual];
  if (textosDelIdioma && textosDelIdioma[clave] !== undefined) {
    return textosDelIdioma[clave];
  }
  if (TEXTOS_POR_IDIOMA.es[clave] !== undefined) {
    return TEXTOS_POR_IDIOMA.es[clave];
  }
  return clave;
}

// Traduce un valor del backend (por ejemplo "operador camion" o "en_curso").
// Si no hay traducción, devuelve el valor tal cual.
function traducirValor(valor) {
  if (valor === null || valor === undefined) {
    return '—';
  }
  const clave = 'e.' + valor;
  if (existeTexto(clave)) {
    return traducir(clave);
  }
  return String(valor);
}

// El backend manda códigos como "OK C ApiAdminSys UsersFetched".
// Las 3 primeras palabras son estado, capa y sistema; la última es el mensaje.
function extraerMensajeDelCodigo(codigo) {
  if (!codigo) {
    return '';
  }
  const palabras = codigo.trim().split(/\s+/);
  const palabrasDelMensaje = palabras.slice(3);
  return palabrasDelMensaje.join('');
}

// Convierte el código del servidor en un texto entendible para la persona.
function traducirCodigoDelServidor(codigo, fueExitoso, estadoHttp) {
  const mensaje = extraerMensajeDelCodigo(codigo);

  // 1) Si hay un texto específico para este mensaje, se usa.
  if (mensaje !== '' && existeTexto('c.' + mensaje)) {
    return traducir('c.' + mensaje);
  }

  // 2) Sin código: mensaje genérico según el resultado.
  if (mensaje === '') {
    if (fueExitoso) {
      return traducir('ok');
    }
    if (estadoHttp >= 500) {
      return traducir('m.server');
    }
    return traducir('err.generic');
  }

  // 3) Con código pero sin texto propio: se mira cómo termina el nombre.
  if (fueExitoso) {
    if (mensaje.endsWith('Created')) {
      return traducir('m.created');
    }
    if (mensaje.endsWith('Updated')) {
      return traducir('m.updated');
    }
    if (mensaje.endsWith('Deleted')) {
      return traducir('m.deleted');
    }
    return traducir('ok');
  }

  if (mensaje.endsWith('NotFound')) {
    return traducir('m.notfound');
  }
  if (mensaje.startsWith('Invalid') || mensaje.includes('Missing') || mensaje.includes('TooLong')) {
    return traducir('m.invalid');
  }
  if (estadoHttp === 403 || mensaje.includes('Denied') || mensaje.includes('Forbidden')) {
    return traducir('m.denied');
  }
  if (estadoHttp === 409) {
    return traducir('m.conflict');
  }
  if (estadoHttp >= 500 || mensaje.endsWith('Error') || mensaje.endsWith('Exception')) {
    return traducir('m.server');
  }
  return traducir('err.generic');
}

// Traduce el HTML estático: elementos con data-i18n (texto), data-i18n-ph (placeholder) y data-i18n-alt (texto alternativo de imágenes).
function traducirPagina() {
  const conTexto = document.querySelectorAll('[data-i18n]');
  for (const elemento of conTexto) {
    elemento.textContent = traducir(elemento.dataset.i18n);
  }

  const conPlaceholder = document.querySelectorAll('[data-i18n-ph]');
  for (const elemento of conPlaceholder) {
    elemento.placeholder = traducir(elemento.dataset.i18nPh);
  }

  const conTextoAlternativo = document.querySelectorAll('[data-i18n-alt]');
  for (const elemento of conTextoAlternativo) {
    elemento.alt = traducir(elemento.dataset.i18nAlt);
  }
}
