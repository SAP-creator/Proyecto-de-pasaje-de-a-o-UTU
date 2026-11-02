/*
  vistas/contenedores.js
  Tabla de contenedores con filtros. El admin general también crea, edita y borra.
*/

function crearVistaContenedores(seccion) {
  const filtroEnFuncion = crearSelect([
    { valor: '', texto: traducir('all') },
    { valor: 'true', texto: traducir('on') },
    { valor: 'false', texto: traducir('off') }
  ]);
  const filtroTipoBasura = crearSelect(opcionesDeLista(TIPOS_BASURA, 'all'));
  const zonaTabla = crearElemento('div', {});

  let botonNuevo = null;
  if (rolActualEs('AP')) {
    botonNuevo = crearBoton('a.nuevo', function () { AccionesContenedor.abrirFormularioEdicion(null, cargarContenedores); }, 'btn pri');
  }

  seccion.append(crearTituloDeSeccion('tab.contenedores', botonNuevo));
  seccion.append(crearElemento('div', { clase: 'filters' },
    crearFiltro('f.estado', filtroEnFuncion),
    crearFiltro('f.tipo', filtroTipoBasura)
  ));
  seccion.append(zonaTabla);

  filtroEnFuncion.addEventListener('change', cargarContenedores);
  filtroTipoBasura.addEventListener('change', cargarContenedores);

  function crearBotonesDeFila(contenedor) {
    const botones = [crearBoton('a.detalle', function () { AccionesContenedor.abrirDetalle(contenedor.ID); })];

    if (rolActualEs('AP')) {
      botones.push(crearBoton('a.editar', function () { AccionesContenedor.abrirFormularioEdicion(contenedor, cargarContenedores); }));
      botones.push(crearBoton('a.borrar', function () { borrarContenedor(contenedor.ID); }, 'danger'));
    }
    return botones;
  }

  async function borrarContenedor(idContenedor) {
    const resultado = await confirmarYEjecutar(traducir('q.borrar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('DELETE', '/contenedor', { CONTENEDOR: { ID: idContenedor } }));
    });
    if (resultado !== null && resultado.exito) {
      cargarContenedores();
    }
  }

  const columnas = [
    { titulo: 'k.ID', valor: function (c) { return '#' + c.ID; } },
    { titulo: 'k.UBICACION', valor: function (c) { return c.UBICACION; } },
    {
      titulo: 'k.EN_FUNCION',
      valor: function (c) {
        if (esVerdadero(c.EN_FUNCION)) {
          return crearElemento('span', { clase: 'tag', texto: traducir('on') });
        }
        return crearElemento('span', { clase: 'tag off', texto: traducir('off') });
      }
    },
    { titulo: 'k.TIPO_BASURAS', valor: function (c) { return crearEtiquetas(c.TIPO_BASURAS); } },
    {
      titulo: 'k.CANTIDAD',
      valor: function (c) {
        if (c.CANTIDAD === null || c.CANTIDAD === undefined) {
          return '—';
        }
        return c.CANTIDAD + ' L';
      }
    },
    { titulo: 'k.CONDICION', valor: function (c) { return crearEtiquetas(c.CONDICION, 'warn'); } },
    { titulo: 'k.BASURA', valor: function (c) { return crearEtiquetas(c.BASURA, 'warn'); } },
    { titulo: 'k.LLENADO', valor: function (c) { return crearBarraDeLlenado(c.LLENADO); } }
  ];

  async function cargarContenedores() {
    mostrarCargando(zonaTabla);

    const filtros = {};
    if (filtroEnFuncion.value !== '') {
      filtros.EN_FUNCION = filtroEnFuncion.value === 'true';
    }
    if (filtroTipoBasura.value !== '') {
      filtros.TIPO_BASURA = filtroTipoBasura.value;
    }

    const respuesta = await Api.llamarComoRol('GET', '/contenedor', filtros);
    if (!respuesta.exito) {
      mostrarMensajeDeError(zonaTabla, respuesta.mensaje);
      return;
    }
    zonaTabla.replaceChildren(construirTabla(columnas, respuesta.lista, crearBotonesDeFila));
  }

  return { mostrar: cargarContenedores };
}

VISTAS.contenedores = crearVistaContenedores;
