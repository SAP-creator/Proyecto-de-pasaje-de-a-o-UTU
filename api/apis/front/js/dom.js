/*
  dom.js
  Funciones pequeñas para crear elementos HTML desde JavaScript.
*/

// Agrega hijos a un elemento. Acepta textos, elementos y listas (que se recorren una por una).
function agregarHijos(elementoPadre, hijos) {
  for (const hijo of hijos) {
    if (hijo === null || hijo === undefined || hijo === false) {
      continue;
    }
    if (Array.isArray(hijo)) {
      agregarHijos(elementoPadre, hijo);
    } else if (hijo.nodeType) {
      elementoPadre.append(hijo);
    } else {
      elementoPadre.append(document.createTextNode(String(hijo)));
    }
  }
}

/*
  Crea un elemento HTML.
    etiqueta:  "div", "button", "input", etc.
    atributos: objeto con los atributos. Nombres especiales:
        clase             -> class
        texto             -> textContent
        claveTexto        -> texto traducido (se guarda en data-i18n)
        clavePlaceholder  -> placeholder traducido
        alHacerClick / alCambiar / alEscribir / alEnviar -> eventos
      Los demás se copian como atributos normales (type, value, href...).
    ...hijos:  texto u otros elementos que van adentro.
*/
function crearElemento(etiqueta, atributos, ...hijos) {
  const elemento = document.createElement(etiqueta);

  if (atributos) {
    for (const nombre of Object.keys(atributos)) {
      const valor = atributos[nombre];

      if (valor === null || valor === undefined || valor === false) {
        continue;
      }

      switch (nombre) {
        case 'clase':
          elemento.className = valor;
          break;
        case 'texto':
          elemento.textContent = valor;
          break;
        case 'claveTexto':
          elemento.dataset.i18n = valor;
          elemento.textContent = traducir(valor);
          break;
        case 'clavePlaceholder':
          elemento.dataset.i18nPh = valor;
          elemento.placeholder = traducir(valor);
          break;
        case 'alHacerClick':
          elemento.addEventListener('click', valor);
          break;
        case 'alCambiar':
          elemento.addEventListener('change', valor);
          break;
        case 'alEscribir':
          elemento.addEventListener('input', valor);
          break;
        case 'alEnviar':
          elemento.addEventListener('submit', valor);
          break;
        default:
          if (valor === true) {
            elemento.setAttribute(nombre, '');
          } else {
            elemento.setAttribute(nombre, valor);
          }
      }
    }
  }

  agregarHijos(elemento, hijos);
  return elemento;
}

function crearOpcion(valor, texto, estaSeleccionada) {
  return crearElemento('option', { value: valor, selected: estaSeleccionada }, texto);
}

// opciones: lista de { valor, texto }. valorActual: el que aparece seleccionado.
function crearSelect(opciones, valorActual) {
  const select = crearElemento('select', {});
  for (const opcion of opciones) {
    const estaSeleccionada = String(opcion.valor) === String(valorActual);
    select.append(crearOpcion(opcion.valor, opcion.texto, estaSeleccionada));
  }
  return select;
}

// Convierte una lista de valores del backend en opciones de un select, con su texto traducido.
// Si se pasa claveTextoVacio, se agrega una primera opción vacía (por ejemplo "Todos").
function opcionesDeLista(listaDeValores, claveTextoVacio) {
  const opciones = [];
  if (claveTextoVacio) {
    opciones.push({ valor: '', texto: traducir(claveTextoVacio) });
  }
  for (const valor of listaDeValores) {
    opciones.push({ valor: valor, texto: traducirValor(valor) });
  }
  return opciones;
}

// Los listados pueden traer una lista real o un texto JSON como '["Vidrio"]'. Siempre devuelve una lista.
function convertirALista(valor) {
  if (Array.isArray(valor)) {
    return valor;
  }
  if (typeof valor === 'string' && valor.startsWith('[')) {
    try {
      return JSON.parse(valor);
    } catch (error) {
      return [valor];
    }
  }
  if (valor === null || valor === undefined || valor === '') {
    return [];
  }
  return [valor];
}

// MySQL devuelve los booleanos como 1/0; este helper acepta todas las formas.
function esVerdadero(valor) {
  return valor === true || valor === 1 || valor === '1' || valor === 'true';
}

// Crea un grupo de etiquetas de colores (para listas de tipos, condiciones, etc.).
function crearEtiquetas(valores, claseExtra) {
  const grupo = crearElemento('div', { clase: 'tags' });
  const lista = convertirALista(valores);
  for (const valor of lista) {
    const clase = 'tag ' + (claseExtra || '');
    grupo.append(crearElemento('span', { clase: clase, texto: traducirValor(valor) }));
  }
  return grupo;
}

function crearBoton(claveTexto, alHacerClick, claseExtra) {
  const clase = 'btn sm ' + (claseExtra || 'link');
  return crearElemento('button', { type: 'button', clase: clase, alHacerClick: alHacerClick }, traducir(claveTexto));
}

// Barra de título de una sección, con controles opcionales a la derecha (por ejemplo un botón "Nuevo").
function crearTituloDeSeccion(claveTitulo, ...controles) {
  return crearElemento('div', { clase: 'sec-head' },
    crearElemento('h2', { claveTexto: claveTitulo }),
    controles
  );
}

// Un control de filtro con su etiqueta arriba.
function crearFiltro(claveEtiqueta, control) {
  return crearElemento('label', { clase: 'f' }, traducir(claveEtiqueta), control);
}

function mostrarCargando(contenedor) {
  contenedor.replaceChildren(crearElemento('div', { clase: 'empty', claveTexto: 'loading' }));
}

function mostrarMensajeDeError(contenedor, mensaje) {
  contenedor.replaceChildren(crearElemento('div', { clase: 'msg error', texto: mensaje }));
}
