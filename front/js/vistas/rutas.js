/*
  vistas/rutas.js
  Rutas de recolección: un centro de inicio, una lista ordenada de contenedores y un centro final.
  Solo el admin general las crea, edita y borra.
*/

function crearVistaRutas(seccion) {
  const zonaTabla = crearElemento('div', {});

  let botonNuevo = null;
  if (rolActualEs('AP')) {
    botonNuevo = crearBoton('a.nuevo', function () { abrirFormularioDeRuta(null); }, 'btn pri');
  }
  seccion.append(crearTituloDeSeccion('tab.rutas', botonNuevo));
  seccion.append(zonaTabla);

  async function pedirDetalleDeRuta(idRuta) {
    const respuesta = await Api.llamarComoRol('GET', '/ruta/data', { RUTA: { ID: idRuta } });
    if (!respuesta.exito) {
      mostrarAviso(respuesta.mensaje, 'error');
      return null;
    }
    if (respuesta.datos.RUTA) {
      return respuesta.datos.RUTA;
    }
    return respuesta.datos;
  }

  // El detalle puede traer los contenedores como ids o como objetos { ID_CONTENEDOR, SLOT }.
  function sacarIdsDeContenedores(detalle) {
    const ids = [];
    for (const elemento of convertirALista(detalle.CONTENEDORES)) {
      if (typeof elemento === 'object' && elemento !== null) {
        if (elemento.ID_CONTENEDOR !== undefined) {
          ids.push(elemento.ID_CONTENEDOR);
        } else {
          ids.push(elemento.ID);
        }
      } else {
        ids.push(elemento);
      }
    }
    return ids;
  }

  // Si el backend devuelve advertencias (por ejemplo un centro que no recibe cierto tipo de basura), se muestran.
  function mostrarAdvertencias(respuesta) {
    const ruta = respuesta.datos.RUTA;
    if (!ruta || !ruta.ADVERTENCIAS) {
      return;
    }
    const advertencias = ruta.ADVERTENCIAS;
    const cantidad = Array.isArray(advertencias) ? advertencias.length : Object.keys(advertencias).length;
    if (cantidad > 0) {
      Modal.abrir(traducir('k.ADVERTENCIAS'), construirDetalle(advertencias));
    }
  }

  async function abrirFormularioDeRuta(rutaExistente) {
    const esNueva = rutaExistente === null;
    const centros = await opcionesDeCentros();
    const contenedores = await opcionesDeContenedores();
    const opcionesParaAgregar = [{ valor: '', texto: '—' }].concat(contenedores);

    let idsDeContenedores = [];
    if (!esNueva) {
      const detalle = await pedirDetalleDeRuta(rutaExistente.ID);
      if (detalle !== null) {
        idsDeContenedores = sacarIdsDeContenedores(detalle);
      }
    }
    const actual = rutaExistente || {};

    abrirFormulario({
      titulo: traducir(esNueva ? 'a.nuevo' : 'a.editar'),
      ancho: true,
      campos: [
        { nombre: 'ID_CENTRO_INICIO', etiqueta: 'k.ID_CENTRO_INICIO', tipo: 'select', opciones: centros, valor: actual.ID_CENTRO_INICIO, obligatorio: true },
        { nombre: 'ID_CENTRO_FINAL', etiqueta: 'k.ID_CENTRO_FINAL', tipo: 'select', opciones: centros, valor: actual.ID_CENTRO_FINAL, obligatorio: true },
        { nombre: 'CONTENEDORES', etiqueta: 'k.CONTENEDORES', tipo: 'listaOrdenada', opciones: opcionesParaAgregar, valor: idsDeContenedores, obligatorio: true }
      ],
      enviar: function (valores) {
        const ruta = {
          ID_CENTRO_INICIO: Number(valores.ID_CENTRO_INICIO),
          ID_CENTRO_FINAL: Number(valores.ID_CENTRO_FINAL),
          CONTENEDORES: valores.CONTENEDORES
        };
        if (esNueva) {
          return Api.llamarComoRol('POST', '/ruta', { RUTA: ruta });
        }
        ruta.ID = rutaExistente.ID;
        return Api.llamarComoRol('PUT', '/ruta', { RUTA: ruta });
      },
      alTerminar: function (respuesta) {
        mostrarAdvertencias(respuesta);
        cargarRutas();
      }
    });
  }

  async function abrirDetalleDeRuta(idRuta) {
    const detalle = await pedirDetalleDeRuta(idRuta);
    if (detalle === null) {
      return;
    }
    const botonMapa = crearBoton('a.vermapa', function () { App.irAPestana('mapa', { ruta: idRuta }); }, 'btn pri');
    Modal.abrir(traducir('k.RUTA') + ' #' + idRuta, [construirDetalle(detalle), botonMapa], { ancho: true });
  }

  async function borrarRuta(idRuta) {
    const resultado = await confirmarYEjecutar(traducir('q.borrar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('DELETE', '/ruta', { RUTA: { ID: idRuta } }));
    });
    if (resultado !== null && resultado.exito) {
      cargarRutas();
    }
  }

  function crearBotonesDeFila(ruta) {
    const botones = [
      crearBoton('a.detalle', function () { abrirDetalleDeRuta(ruta.ID); }),
      crearBoton('a.vermapa', function () { App.irAPestana('mapa', { ruta: ruta.ID }); })
    ];
    if (rolActualEs('AP')) {
      botones.push(crearBoton('a.editar', function () { abrirFormularioDeRuta(ruta); }));
      botones.push(crearBoton('a.borrar', function () { borrarRuta(ruta.ID); }, 'danger'));
    }
    return botones;
  }

  const columnas = [
    { titulo: 'k.ID', valor: function (r) { return '#' + r.ID; } },
    { titulo: 'k.ID_CENTRO_INICIO', valor: function (r) { return '#' + r.ID_CENTRO_INICIO; } },
    { titulo: 'k.ID_CENTRO_FINAL', valor: function (r) { return '#' + r.ID_CENTRO_FINAL; } },
    { titulo: 'k.CANTIDAD_CONTENEDORES', valor: function (r) { return r.CANTIDAD_CONTENEDORES; } }
  ];

  async function cargarRutas() {
    mostrarCargando(zonaTabla);
    const respuesta = await Api.llamarComoRol('GET', '/ruta');
    if (!respuesta.exito) {
      mostrarMensajeDeError(zonaTabla, respuesta.mensaje);
      return;
    }
    zonaTabla.replaceChildren(construirTabla(columnas, respuesta.lista, crearBotonesDeFila));
  }

  return { mostrar: cargarRutas };
}

VISTAS.rutas = crearVistaRutas;
