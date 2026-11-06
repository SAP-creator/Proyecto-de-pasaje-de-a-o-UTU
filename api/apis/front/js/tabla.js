/*
  tabla.js
  Construye tablas y listados de detalle a partir de datos del backend.
*/

// Muestra un valor cualquiera como algo legible: objetos como detalle, booleanos como Sí/No.
function formatearValor(valor) {
  if (valor === null || valor === undefined) {
    return '—';
  }
  if (typeof valor === 'boolean') {
    if (valor) {
      return traducir('yes');
    }
    return traducir('no');
  }
  if (typeof valor === 'object') {
    return construirDetalle(valor);
  }
  return String(valor);
}

// Arma el texto de un nombre de campo: usa la traducción "k.NOMBRE" si existe.
function etiquetaDeCampo(nombre) {
  if (existeTexto('k.' + nombre)) {
    return traducir('k.' + nombre);
  }
  return nombre;
}

function esValorSimple(valor) {
  return valor === null || typeof valor !== 'object';
}

// Muestra un objeto (o lista) como pares "nombre: valor". Sirve para pantallas de detalle.
function construirDetalle(valor) {
  if (Array.isArray(valor)) {
    let todosSimples = true;
    for (const elemento of valor) {
      if (!esValorSimple(elemento)) {
        todosSimples = false;
      }
    }

    if (todosSimples) {
      const textos = [];
      for (const elemento of valor) {
        textos.push(traducirValor(elemento));
      }
      return textos.join(', ');
    }

    const contenedor = crearElemento('div', {});
    for (const elemento of valor) {
      contenedor.append(construirDetalle(elemento));
    }
    return contenedor;
  }

  const lista = crearElemento('dl', { clase: 'kv' });
  for (const nombre of Object.keys(valor)) {
    lista.append(crearElemento('dt', { texto: etiquetaDeCampo(nombre) }));
    lista.append(crearElemento('dd', {}, formatearValor(valor[nombre])));
  }
  return lista;
}

/*
  Construye una tabla.
    columnas:       lista de { titulo: "k.ID", valor: function (fila) { ... } }
                    "titulo" es la clave de traducción; "valor" devuelve el texto o elemento de la celda.
    filas:          lista de objetos, uno por fila.
    crearAcciones:  (opcional) function (fila) que devuelve una lista de botones para la última columna.
*/
function construirTabla(columnas, filas, crearAcciones) {
  if (filas.length === 0) {
    return crearElemento('div', { clase: 'empty', claveTexto: 'empty' });
  }

  const filaDeTitulos = crearElemento('tr', {});
  for (const columna of columnas) {
    filaDeTitulos.append(crearElemento('th', { texto: traducir(columna.titulo) }));
  }
  if (crearAcciones) {
    filaDeTitulos.append(crearElemento('th', {}));
  }

  const cuerpo = crearElemento('tbody', {});
  for (const fila of filas) {
    const filaHtml = crearElemento('tr', {});

    for (const columna of columnas) {
      let contenido = columna.valor(fila);
      if (contenido === null || contenido === undefined) {
        contenido = '—';
      }
      filaHtml.append(crearElemento('td', {}, contenido));
    }

    if (crearAcciones) {
      const botones = crearAcciones(fila);
      filaHtml.append(crearElemento('td', { clase: 'acts' }, botones));
    }

    cuerpo.append(filaHtml);
  }

  const tabla = crearElemento('table', {}, crearElemento('thead', {}, filaDeTitulos), cuerpo);
  return crearElemento('div', { clase: 'tbl-wrap' }, tabla);
}

// Tabla con una columna por cada campo que aparezca en las filas. Útil cuando no se conocen los campos.
function construirTablaAutomatica(filas) {
  const nombres = [];
  for (const fila of filas) {
    for (const nombre of Object.keys(fila)) {
      if (!nombres.includes(nombre)) {
        nombres.push(nombre);
      }
    }
  }

  const columnas = [];
  for (const nombre of nombres) {
    columnas.push({
      titulo: existeTexto('k.' + nombre) ? 'k.' + nombre : nombre,
      valor: function (fila) {
        return formatearValor(fila[nombre]);
      }
    });
  }
  return construirTabla(columnas, filas);
}

// Barra horizontal de progreso. porcentaje va de 0 a 100.
function crearBarra(porcentaje, color) {
  let ancho = porcentaje;
  if (ancho > 100) {
    ancho = 100;
  }
  if (isNaN(ancho)) {
    ancho = 0;
  }
  let estilo = 'width:' + ancho + '%';
  if (color) {
    estilo = estilo + ';background:' + color;
  }
  return crearElemento('div', { clase: 'bar' }, crearElemento('i', { style: estilo }));
}
