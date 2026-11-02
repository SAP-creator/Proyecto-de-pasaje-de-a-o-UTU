/*
  vistas/incidentes.js
  Incidentes reportados sobre contenedores. Todos pueden reportar (incidente, estado y,
  si es operador de camión, llenado); 
  solo admin operador y admin general los editan o borran.
*/

function crearVistaIncidentes(seccion) {
  const filtroEstado = crearSelect(opcionesDeLista(ESTADOS_INCIDENTE, 'all'));
  const filtroContenedor = crearElemento('input', { type: 'number', min: 1, clavePlaceholder: 'f.idcont' });
  const zonaTabla = crearElemento('div', {});
  const puedeGestionar = rolActualEs('AO', 'AP');

  seccion.append(crearTituloDeSeccion('tab.incidentes', crearBoton('a.reportar', abrirFormularioNuevo, 'btn pri')));
  seccion.append(crearElemento('div', { clase: 'filters' },
    crearFiltro('f.estado', filtroEstado),
    crearFiltro('f.idcont', filtroContenedor)
  ));
  seccion.append(zonaTabla);

  filtroEstado.addEventListener('change', cargarIncidentes);
  filtroContenedor.addEventListener('change', cargarIncidentes);

  // Un solo formulario para incidente, estado y llenado (lo que se deja vacío no se envía).
  // Es el mismo que se abre desde el detalle de un contenedor en el mapa.
  async function abrirFormularioNuevo() {
    const contenedores = await opcionesDeContenedores();
    AccionesContenedor.reportarTodo(null, cargarIncidentes, contenedores);
  }

  function abrirFormularioEdicion(incidente) {
    abrirFormulario({
      titulo: traducir('a.editar'),
      campos: [
        { nombre: 'TIPO_INCIDENTE', etiqueta: 'k.TIPO_INCIDENTE', tipo: 'select', opciones: opcionesDeLista(GRAVEDADES_INCIDENTE), valor: incidente.TIPO_INCIDENTE },
        { nombre: 'ESTADO_INCIDENTE', etiqueta: 'k.ESTADO_INCIDENTE', tipo: 'select', opciones: opcionesDeLista(ESTADOS_INCIDENTE), valor: incidente.ESTADO_INCIDENTE },
        { nombre: 'DESCRIPCION', etiqueta: 'k.DESCRIPCION', tipo: 'textoLargo', valor: incidente.DESCRIPCION }
      ],
      enviar: function (valores) {
        valores.ID = incidente.ID;
        return Api.llamarComoRol('PUT', '/contenedor/incidente', { INCIDENTE: valores });
      },
      alTerminar: cargarIncidentes
    });
  }

  async function borrarIncidente(idIncidente) {
    const resultado = await confirmarYEjecutar(traducir('q.borrar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('DELETE', '/contenedor/incidente', { INCIDENTE: { ID: idIncidente } }));
    });
    if (resultado !== null && resultado.exito) {
      cargarIncidentes();
    }
  }

  function crearBotonesDeFila(incidente) {
    return [
      crearBoton('a.editar', function () { abrirFormularioEdicion(incidente); }),
      crearBoton('a.borrar', function () { borrarIncidente(incidente.ID); }, 'danger')
    ];
  }

  function crearEtiquetaDeGravedad(incidente) {
    let clase = 'tag';
    if (incidente.TIPO_INCIDENTE === 'Grave') {
      clase = 'tag bad';
    } else if (incidente.TIPO_INCIDENTE === 'Moderado') {
      clase = 'tag warn';
    }
    return crearElemento('span', { clase: clase, texto: traducirValor(incidente.TIPO_INCIDENTE) });
  }

  const columnas = [
    { titulo: 'k.ID', valor: function (i) { return '#' + i.ID; } },
    { titulo: 'k.CONTENEDOR', valor: function (i) { return '#' + i.ID_CONTENEDOR; } },
    { titulo: 'k.TIPO_INCIDENTE', valor: crearEtiquetaDeGravedad },
    { titulo: 'k.ESTADO_INCIDENTE', valor: function (i) { return traducirValor(i.ESTADO_INCIDENTE); } },
    { titulo: 'k.DESCRIPCION', valor: function (i) { return i.DESCRIPCION || '—'; } }
  ];

  async function cargarIncidentes() {
    mostrarCargando(zonaTabla);

    const filtros = {};
    if (filtroEstado.value !== '') {
      filtros.ESTADO_INCIDENTE = filtroEstado.value;
    }
    if (filtroContenedor.value !== '') {
      filtros.ID_CONTENEDOR = Number(filtroContenedor.value);
    }

    const respuesta = await Api.llamarComoRol('GET', '/contenedor/incidente', filtros);
    if (!respuesta.exito) {
      mostrarMensajeDeError(zonaTabla, respuesta.mensaje);
      return;
    }

    let funcionDeBotones = null;
    if (puedeGestionar) {
      funcionDeBotones = crearBotonesDeFila;
    }
    zonaTabla.replaceChildren(construirTabla(columnas, respuesta.lista, funcionDeBotones));
  }

  return { mostrar: cargarIncidentes };
}

VISTAS.incidentes = crearVistaIncidentes;
