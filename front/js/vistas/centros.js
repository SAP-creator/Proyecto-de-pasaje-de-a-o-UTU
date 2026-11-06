/*
  vistas/centros.js
  Centros de acopio y sus almacenamientos.
    Admin general: crea, edita y borra centros.
    Admin operador: registra retiros.
    Admin operador y admin general: consultan el historial de retiros.
*/

function crearVistaCentros(seccion) {
  const zonaTabla = crearElemento('div', {});

  let botonNuevo = null;
  if (rolActualEs('AP')) {
    botonNuevo = crearBoton('a.nuevo', function () { abrirFormularioDeCentro(null); }, 'btn pri');
  }
  seccion.append(crearTituloDeSeccion('tab.centros', botonNuevo));
  seccion.append(zonaTabla);

  // Pide el detalle de un centro (con sus almacenamientos).
  async function pedirDetalleDelCentro(idCentro) {
    const respuesta = await Api.llamarComoRol('GET', '/centro/data', { CENTRO: { ID: idCentro } });
    if (!respuesta.exito) {
      mostrarAviso(respuesta.mensaje, 'error');
      return null;
    }
    if (respuesta.datos.CENTRO) {
      return respuesta.datos.CENTRO;
    }
    return respuesta.datos;
  }

  async function abrirFormularioDeCentro(centroExistente) {
    const esNuevo = centroExistente === null;
    let detalle = {};

    if (!esNuevo) {
      detalle = await pedirDetalleDelCentro(centroExistente.ID);
      if (detalle === null) {
        return;
      }
    }

    abrirFormulario({
      titulo: traducir(esNuevo ? 'a.nuevo' : 'a.editar'),
      ancho: true,
      campos: [
        { nombre: 'UBICACION', etiqueta: 'k.UBICACION', tipo: 'ubicacion', valor: detalle.UBICACION, obligatorio: true },
        { nombre: 'ALMACENAMIENTOS', etiqueta: 'k.ALMACENAMIENTOS', tipo: 'almacenamientos', valor: detalle.ALMACENAMIENTOS, obligatorio: true }
      ],
      enviar: function (valores) {
        if (esNuevo) {
          return Api.llamarComoRol('POST', '/centro', { CENTRO: valores });
        }
        valores.ID = centroExistente.ID;
        return Api.llamarComoRol('PUT', '/centro', { CENTRO: valores });
      },
      alTerminar: cargarCentros
    });
  }

  async function abrirHistorialDeRetiros(idCentro) {
    const respuesta = await Api.llamarComoRol('GET', '/centro/retiro', { CENTRO: { ID: idCentro } });
    let contenido;
    if (respuesta.exito) {
      contenido = construirTablaAutomatica(respuesta.lista);
    } else {
      contenido = crearElemento('div', { clase: 'msg error', texto: respuesta.mensaje });
    }
    Modal.abrir(traducir('a.retiros'), contenido, { ancho: true });
  }

  function abrirFormularioDeRetiro(idCentro, almacenamiento) {
    abrirFormulario({
      titulo: traducir('a.retirar'),
      campos: [
        { nombre: 'VOLUMEN', etiqueta: 'k.VOLUMEN', tipo: 'numero', min: 0, paso: 'any', ayuda: 'hint.retiro' }
      ],
      enviar: function (valores) {
        const centro = { ID: idCentro, ID_ALMACENAMIENTO: almacenamiento.ID };
        if (valores.VOLUMEN) {
          centro.VOLUMEN = Number(valores.VOLUMEN);
        }
        return Api.llamarComoRol('POST', '/centro/retiro', { CENTRO: centro });
      }
    });
  }

  function abrirFormularioDeAjuste(idCentro, almacenamiento) {
    abrirFormulario({
      titulo: traducir('a.ajustar'),
      campos: [
        { nombre: 'VOLUMEN_ACTUAL', etiqueta: 'k.VOLUMEN_ACTUAL', tipo: 'numero', min: 0, max: almacenamiento.CANTIDAD, paso: 'any', valor: almacenamiento.VOLUMEN_ACTUAL || 0 }
      ],
      enviar: function (valores) {
        return Api.llamarComoRol('POST', '/centro/ajuste', { CENTRO: {
          ID: idCentro,
          ID_ALMACENAMIENTO: almacenamiento.ID,
          VOLUMEN_ACTUAL: Number(valores.VOLUMEN_ACTUAL)
        } });
      },
      alTerminar: cargarCentros
    });
  }

  async function abrirDetalleDelCentro(idCentro) {
    const centro = await pedirDetalleDelCentro(idCentro);
    if (centro === null) {
      return;
    }
    const almacenamientos = convertirALista(centro.ALMACENAMIENTOS);

    const columnas = [
      { titulo: 'k.TIPO_BASURAS', valor: function (a) { return crearEtiquetas(a.TIPO_BASURAS); } },
      { titulo: 'k.CANTIDAD', valor: function (a) { return a.CANTIDAD + ' L'; } },
      {
        titulo: 'k.VOLUMEN_ACTUAL',
        valor: function (a) {
          const volumen = a.VOLUMEN_ACTUAL || 0;
          const porcentaje = (volumen / a.CANTIDAD) * 100;
          return crearElemento('div', {}, volumen + ' L', crearBarra(porcentaje));
        }
      }
    ];

    let funcionDeBotones = null;
    if (rolActualEs('O', 'AO')) {
      funcionDeBotones = function (almacenamiento) {
        const botones = [crearBoton('a.ajustar', function () { abrirFormularioDeAjuste(idCentro, almacenamiento); })];
        if (rolActualEs('AO')) {
          botones.push(crearBoton('a.retirar', function () { abrirFormularioDeRetiro(idCentro, almacenamiento); }));
        }
        return botones;
      };
    }

    const contenido = [
      crearElemento('p', { texto: centro.UBICACION || '' }),
      construirTabla(columnas, almacenamientos, funcionDeBotones)
    ];
    if (rolActualEs('AO', 'AP')) {
      contenido.push(crearBoton('a.retiros', function () { abrirHistorialDeRetiros(idCentro); }, 'btn'));
    }

    Modal.abrir(traducir('k.CENTRO') + ' #' + idCentro, contenido, { ancho: true });
  }

  async function borrarCentro(idCentro) {
    const resultado = await confirmarYEjecutar(traducir('q.borrar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('DELETE', '/centro', { CENTRO: { ID: idCentro } }));
    });
    if (resultado !== null && resultado.exito) {
      cargarCentros();
    }
  }

  function crearBotonesDeFila(centro) {
    const botones = [crearBoton('a.detalle', function () { abrirDetalleDelCentro(centro.ID); })];
    if (rolActualEs('AP')) {
      botones.push(crearBoton('a.editar', function () { abrirFormularioDeCentro(centro); }));
      botones.push(crearBoton('a.borrar', function () { borrarCentro(centro.ID); }, 'danger'));
    }
    return botones;
  }

  const columnas = [
    { titulo: 'k.ID', valor: function (c) { return '#' + c.ID; } },
    { titulo: 'k.UBICACION', valor: function (c) { return c.UBICACION; } },
    {
      titulo: 'k.VOLUMEN_ACTUAL',
      valor: function (c) {
        const almacenamientos = convertirALista(c.ALMACENAMIENTOS);
        if (almacenamientos.length === 0) { return '—'; }
        return crearElemento('div', {}, almacenamientos.map(function (a) {
          return crearElemento('div', {}, crearEtiquetas(a.TIPO_BASURAS), (a.VOLUMEN_ACTUAL || 0) + ' / ' + a.CANTIDAD + ' L');
        }));
      }
    }
  ];

  async function cargarCentros() {
    mostrarCargando(zonaTabla);
    const respuesta = await Api.llamarComoRol('GET', '/centro');
    if (!respuesta.exito) {
      mostrarMensajeDeError(zonaTabla, respuesta.mensaje);
      return;
    }
    zonaTabla.replaceChildren(construirTabla(columnas, respuesta.lista, crearBotonesDeFila));
  }

  return { mostrar: cargarCentros };
}

VISTAS.centros = crearVistaCentros;
