/*
  formulario.js
  Formularios dentro de una ventana (modal).

  Uso:
    abrirFormulario({
      titulo: 'Nuevo camión',
      campos: [
        { nombre: 'MATRICULA', etiqueta: 'k.MATRICULA', tipo: 'texto', obligatorio: true },
        ...
      ],
      enviar: function (valores) { return Api.llamarComoRol('POST', '/camion', { CAMION: valores }); },
      alTerminar: function () { recargarLaTabla(); }
    });

  Propiedades de cada campo:
    nombre        clave con la que se entrega el valor a "enviar"
    etiqueta      clave de traducción del título del campo
    tipo          texto | numero | fecha | password | textoLargo | select | casilla |
                  variasOpciones | ubicacion | listaOrdenada | almacenamientos
    valor         valor inicial
    opciones      lista de { valor, texto } (select, variasOpciones, listaOrdenada)
    obligatorio   true si no se puede dejar vacío
    ayuda         clave de traducción de un texto de ayuda bajo el campo
    mitad         true para ocupar media fila
    ejemplo       texto de ejemplo dentro del campo
    min, max, paso  límites de los campos numéricos
    incluirSiVacio  true para enviar el campo aunque esté vacío (por ejemplo una casilla desmarcada)

  Opciones del formulario: titulo, campos, enviar, alTerminar,
    ancho (ventana más ancha) y sinCerrar (no se puede cancelar; para datos obligatorios).

  Cada creador de campo devuelve { elemento, obtenerValor }:
    elemento       lo que se muestra en el formulario
    obtenerValor   función que devuelve lo que la persona completó
*/

// ---------- Campos simples ----------

function crearCampoEntrada(config, tipoEntrada) {
  let valorInicial = '';
  if (config.valor !== undefined && config.valor !== null) {
    valorInicial = config.valor;
  }

  const entrada = crearElemento('input', {
    type: tipoEntrada,
    value: valorInicial,
    placeholder: config.ejemplo,
    min: config.min,
    max: config.max,
    step: config.paso,
    autocomplete: 'off'
  });

  return {
    elemento: entrada,
    obtenerValor: function () {
      return entrada.value.trim();
    }
  };
}

function crearCampoTextoLargo(config) {
  let valorInicial = '';
  if (config.valor !== undefined && config.valor !== null) {
    valorInicial = config.valor;
  }
  const area = crearElemento('textarea', {}, valorInicial);

  return {
    elemento: area,
    obtenerValor: function () {
      return area.value.trim();
    }
  };
}

function crearCampoSelect(config) {
  const select = crearSelect(config.opciones, config.valor);

  return {
    elemento: select,
    obtenerValor: function () {
      return select.value;
    }
  };
}

function crearCampoCasilla(config) {
  const casilla = crearElemento('input', { type: 'checkbox', checked: Boolean(config.valor) });

  return {
    elemento: crearElemento('div', {}, casilla),
    obtenerValor: function () {
      return casilla.checked;
    }
  };
}

// Varias casillas; devuelve la lista de valores marcados.
function crearCampoVariasOpciones(config) {
  const valoresMarcados = convertirALista(config.valor).map(String);
  const casillas = [];
  const contenedor = crearElemento('div', { clase: 'checks' });

  for (const opcion of config.opciones) {
    const estaMarcada = valoresMarcados.includes(String(opcion.valor));
    const casilla = crearElemento('input', { type: 'checkbox', value: opcion.valor, checked: estaMarcada });
    casillas.push(casilla);
    contenedor.append(crearElemento('label', {}, casilla, opcion.texto));
  }

  return {
    elemento: contenedor,
    obtenerValor: function () {
      const marcados = [];
      for (const casilla of casillas) {
        if (casilla.checked) {
          marcados.push(casilla.value);
        }
      }
      return marcados;
    }
  };
}

// ---------- Ubicación con mapa ----------

// Un texto "lat,lng" y un botón que abre un mapa para elegir el punto con un clic.
function crearCampoUbicacion(config) {
  let valorInicial = '';
  if (config.valor) {
    valorInicial = config.valor;
  }

  const entrada = crearElemento('input', {
    value: valorInicial,
    placeholder: 'lat,lng  (-34.9011,-56.1645)',
    autocomplete: 'off'
  });
  const cajaDelMapa = crearElemento('div', { clase: 'hidden' });
  let mapa = null;
  let marcador = null;

  function elegirPunto(punto) {
    entrada.value = formatearUbicacion(punto);
    if (marcador !== null) {
      marcador.setLatLng([punto.lat, punto.lng]);
    }
  }

  async function mostrarOcultarMapa() {
    cajaDelMapa.classList.toggle('hidden');
    const quedoOculto = cajaDelMapa.classList.contains('hidden');
    if (quedoOculto) {
      return;
    }
    if (mapa !== null) {
      // El mapa ya existe: se recalcula su tamaño porque estuvo oculto.
      mapa.invalidateSize();
      return;
    }

    // Primera vez que se abre: se crea el mapa.
    const contenedorDelMapa = crearElemento('div', { clase: 'map-pick' });
    cajaDelMapa.append(contenedorDelMapa);

    try {
      await Mapas.cargar();
    } catch (error) {
      contenedorDelMapa.textContent = traducir('map.err');
      return;
    }

    let centro = leerUbicacion(entrada.value);
    if (centro === null) {
      centro = CONFIGURACION.CENTRO_DEL_MAPA;
    }

    mapa = Mapas.crear(contenedorDelMapa, centro, 13);
    marcador = Mapas.crearMarcador(mapa, centro, '#2f6f2a', false, null);
    mapa.on('click', function (evento) {
      elegirPunto({ lat: evento.latlng.lat, lng: evento.latlng.lng });
    });
  }

  const botonMapa = crearElemento('button', { type: 'button', clase: 'btn sm', alHacerClick: mostrarOcultarMapa }, traducir('pickmap'));
  const filaDelBoton = crearElemento('div', { clase: 'row-btns', style: 'justify-content:flex-start;margin-top:.4rem' }, botonMapa);

  return {
    elemento: crearElemento('div', {}, entrada, filaDelBoton, cajaDelMapa),
    obtenerValor: function () {
      return entrada.value.trim();
    }
  };
}

// ---------- Lista ordenada de ids (contenedores de una ruta) ----------

function crearCampoListaOrdenada(config) {
  const idsElegidos = [];
  for (const id of convertirALista(config.valor)) {
    idsElegidos.push(Number(id));
  }

  const cajaDeLista = crearElemento('div', { clase: 'ilist' });
  const selectorParaAgregar = crearSelect(config.opciones);

  function textoDelId(id) {
    for (const opcion of config.opciones) {
      if (Number(opcion.valor) === id) {
        return opcion.texto;
      }
    }
    return '#' + id;
  }

  function subir(posicion) {
    const anterior = idsElegidos[posicion - 1];
    idsElegidos[posicion - 1] = idsElegidos[posicion];
    idsElegidos[posicion] = anterior;
    dibujarLista();
  }

  function bajar(posicion) {
    const siguiente = idsElegidos[posicion + 1];
    idsElegidos[posicion + 1] = idsElegidos[posicion];
    idsElegidos[posicion] = siguiente;
    dibujarLista();
  }

  function quitar(posicion) {
    idsElegidos.splice(posicion, 1);
    dibujarLista();
  }

  function agregarSeleccionado() {
    const id = Number(selectorParaAgregar.value);
    if (id && !idsElegidos.includes(id)) {
      idsElegidos.push(id);
      dibujarLista();
    }
  }

  function dibujarLista() {
    cajaDeLista.replaceChildren();

    for (let posicion = 0; posicion < idsElegidos.length; posicion++) {
      const id = idsElegidos[posicion];
      const esLaPrimera = posicion === 0;
      const esLaUltima = posicion === idsElegidos.length - 1;

      const botonSubir = crearElemento('button', {
        type: 'button', clase: 'btn sm ghost', disabled: esLaPrimera,
        alHacerClick: function () { subir(posicion); }
      }, '↑');
      const botonBajar = crearElemento('button', {
        type: 'button', clase: 'btn sm ghost', disabled: esLaUltima,
        alHacerClick: function () { bajar(posicion); }
      }, '↓');
      const botonQuitar = crearElemento('button', {
        type: 'button', clase: 'btn sm danger',
        alHacerClick: function () { quitar(posicion); }
      }, '✕');

      const texto = crearElemento('span', { texto: (posicion + 1) + '. ' + textoDelId(id) });
      cajaDeLista.append(crearElemento('div', { clase: 'it' }, texto, botonSubir, botonBajar, botonQuitar));
    }
  }

  dibujarLista();

  const botonAgregar = crearElemento('button', { type: 'button', clase: 'btn sm', alHacerClick: agregarSeleccionado }, traducir('add'));
  const filaParaAgregar = crearElemento('div', { clase: 'row-btns', style: 'justify-content:flex-start' }, selectorParaAgregar, botonAgregar);

  return {
    elemento: crearElemento('div', { clase: 'sub' }, cajaDeLista, filaParaAgregar),
    obtenerValor: function () {
      return idsElegidos.slice();
    }
  };
}

// ---------- Almacenamientos de un centro de acopio ----------

// Cada fila tiene los tipos de basura que guarda y su capacidad en litros.
function crearCampoAlmacenamientos(config) {
  const contenedor = crearElemento('div', { clase: 'sub' });
  const filas = [];

  const opcionesDeTipos = [];
  for (const tipo of TIPOS_BASURA) {
    opcionesDeTipos.push({ valor: tipo, texto: traducirValor(tipo) });
  }

  function agregarFila(almacenamiento) {
    const datos = almacenamiento || {};
    const campoTipos = crearCampoVariasOpciones({ opciones: opcionesDeTipos, valor: datos.TIPO_BASURAS });
    const campoCantidad = crearCampoEntrada({ valor: datos.CANTIDAD, min: 1, paso: 'any', ejemplo: traducir('f.cantidad') }, 'number');

    const fila = { id: datos.ID, campoTipos: campoTipos, campoCantidad: campoCantidad, elemento: null };

    const botonQuitar = crearElemento('button', {
      type: 'button', clase: 'btn sm danger',
      alHacerClick: function () {
        filas.splice(filas.indexOf(fila), 1);
        fila.elemento.remove();
      }
    }, traducir('remove'));

    fila.elemento = crearElemento('div', { clase: 'sub' }, campoTipos.elemento, campoCantidad.elemento, botonQuitar);
    filas.push(fila);
    contenedor.insertBefore(fila.elemento, botonAgregar);
  }

  const botonAgregar = crearElemento('button', { type: 'button', clase: 'btn sm', alHacerClick: function () { agregarFila(); } }, traducir('add'));
  contenedor.append(botonAgregar);

  const almacenamientosIniciales = convertirALista(config.valor);
  for (const almacenamiento of almacenamientosIniciales) {
    agregarFila(almacenamiento);
  }
  if (filas.length === 0) {
    agregarFila();
  }

  return {
    elemento: contenedor,
    obtenerValor: function () {
      const resultado = [];
      for (const fila of filas) {
        const almacenamiento = {
          TIPO_BASURAS: fila.campoTipos.obtenerValor(),
          CANTIDAD: Number(fila.campoCantidad.obtenerValor())
        };
        if (fila.id) {
          almacenamiento.ID = fila.id;
        }
        resultado.push(almacenamiento);
      }
      return resultado;
    }
  };
}

// ---------- Formulario completo ----------

function crearCampo(config) {
  switch (config.tipo) {
    case 'numero':
      return crearCampoEntrada(config, 'number');
    case 'fecha':
      return crearCampoEntrada(config, 'date');
    case 'password':
      return crearCampoEntrada(config, 'password');
    case 'textoLargo':
      return crearCampoTextoLargo(config);
    case 'select':
      return crearCampoSelect(config);
    case 'casilla':
      return crearCampoCasilla(config);
    case 'variasOpciones':
      return crearCampoVariasOpciones(config);
    case 'ubicacion':
      return crearCampoUbicacion(config);
    case 'listaOrdenada':
      return crearCampoListaOrdenada(config);
    case 'almacenamientos':
      return crearCampoAlmacenamientos(config);
    default:
      return crearCampoEntrada(config, 'text');
  }
}

function estaVacio(valor) {
  if (valor === '') {
    return true;
  }
  if (Array.isArray(valor) && valor.length === 0) {
    return true;
  }
  return false;
}

// Respuesta que se devuelve desde "enviar" cuando la persona no completó nada para cambiar.
function respuestaSinCambios() {
  return { exito: false, mensaje: traducir('nothing') };
}

function abrirFormulario(opciones) {
  // 1) Crear cada campo.
  const campos = [];
  for (const config of opciones.campos) {
    campos.push({ config: config, campo: crearCampo(config) });
  }

  const cajaDeError = crearElemento('div', { clase: 'msg error hidden', role: 'alert' });
  const botonGuardar = crearElemento('button', { type: 'submit', clase: 'btn pri' }, traducir('save'));
  const ventana = Modal.abrir(opciones.titulo, null, { ancho: opciones.ancho, sePuedeCerrar: !opciones.sinCerrar });

  function mostrarError(texto) {
    cajaDeError.textContent = texto;
    cajaDeError.classList.remove('hidden');
  }

  // 2) Al enviar: validar, juntar los valores y llamar a "enviar".
  async function alEnviarFormulario(evento) {
    evento.preventDefault();
    cajaDeError.classList.add('hidden');

    const valores = {};
    for (const item of campos) {
      const valor = item.campo.obtenerValor();
      const vacio = estaVacio(valor);

      if (item.config.obligatorio && vacio) {
        mostrarError(traducir('req') + ': ' + traducir(item.config.etiqueta));
        return;
      }
      if (!vacio || item.config.incluirSiVacio) {
        valores[item.config.nombre] = valor;
      }
    }

    botonGuardar.disabled = true;
    const respuesta = await opciones.enviar(valores);
    botonGuardar.disabled = false;

    if (!respuesta.exito) {
      mostrarError(respuesta.mensaje);
      return;
    }

    ventana.cerrar();
    mostrarAviso(respuesta.mensaje, 'ok');
    if (opciones.alTerminar) {
      opciones.alTerminar(respuesta);
    }
  }

  // 3) Armar el formulario en pantalla.
  const formulario = crearElemento('form', { clase: 'grid2', novalidate: true, alEnviar: alEnviarFormulario });

  for (const item of campos) {
    let clase = 'f span2';
    if (item.config.mitad) {
      clase = 'f';
    }

    let textoDeEtiqueta = traducir(item.config.etiqueta);
    if (item.config.obligatorio) {
      textoDeEtiqueta = textoDeEtiqueta + ' *';
    }

    const etiqueta = crearElemento('label', { clase: clase }, textoDeEtiqueta, item.campo.elemento);
    if (item.config.ayuda) {
      etiqueta.append(crearElemento('span', { clase: 'hint', texto: traducir(item.config.ayuda) }));
    }
    formulario.append(etiqueta);
  }

  formulario.append(crearElemento('div', { clase: 'span2' }, cajaDeError));

  const filaDeBotones = crearElemento('div', { clase: 'span2 row-btns' });
  if (!opciones.sinCerrar) {
    filaDeBotones.append(crearElemento('button', { type: 'button', clase: 'btn', alHacerClick: ventana.cerrar }, traducir('cancel')));
  }
  filaDeBotones.append(botonGuardar);
  formulario.append(filaDeBotones);

  ventana.cuerpo.append(formulario);
  return ventana;
}
