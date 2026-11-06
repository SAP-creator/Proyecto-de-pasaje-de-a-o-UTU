/*
  vistas/ejecuciones.js
  Ejecuciones: una salida concreta de un trayecto en una fecha, con un camión y un operario.

  Quién hace qué:
    AO (admin operador): aprobar y rechazar
    O  (operador):       iniciar, pausar, reanudar, terminar y cancelar; al iniciar o reanudar
                         se abre la pantalla de ruta (modo-ruta.js)
  Estados: aprobada -> en_curso -> pausada <-> en_curso -> finalizada
*/

function crearVistaEjecuciones(seccion) {
  const filtroEstado = crearSelect(opcionesDeLista(ESTADOS_EJECUCION, 'all'));
  const zonaTabla = crearElemento('div', {});

  let botonAprobar = null;
  if (rolActualEs('AO')) {
    botonAprobar = crearBoton('a.aprobar', abrirFormularioDeAprobacion, 'btn pri');
  }
  seccion.append(crearTituloDeSeccion('tab.ejecuciones', botonAprobar));
  seccion.append(crearElemento('div', { clase: 'filters' }, crearFiltro('f.estado', filtroEstado)));
  seccion.append(zonaTabla);

  filtroEstado.addEventListener('change', cargarEjecuciones);

  // Aprobar una ejecución nueva (solo admin operador).
  async function abrirFormularioDeAprobacion() {
    const trayectos = await pedirLista('/trayecto');
    const camiones = await pedirLista('/camion');

    const opcionesDeTrayectos = [];
    for (const trayecto of trayectos) {
      let cuando = trayecto.FECHA_ESPECIAL;
      if (trayecto.DIA_SEMANA) {
        cuando = traducirValor(trayecto.DIA_SEMANA);
      }
      const texto = '#' + trayecto.ID + ' · ' + traducir('k.RUTA') + ' ' + trayecto.ID_RUTA + ' · ' + cuando + ' ' + trayecto.HORA;
      opcionesDeTrayectos.push({ valor: trayecto.ID, texto: texto });
    }

    const opcionesDeCamiones = [];
    for (const camion of camiones) {
      const texto = '#' + camion.ID + ' ' + camion.MATRICULA + ' · ' + traducirValor(estadoDelCamion(camion));
      opcionesDeCamiones.push({ valor: camion.ID, texto: texto });
    }

    abrirFormulario({
      titulo: traducir('a.aprobar'),
      campos: [
        { nombre: 'ID_TRAYECTO', etiqueta: 'k.ID_TRAYECTO', tipo: 'select', opciones: opcionesDeTrayectos, obligatorio: true },
        { nombre: 'FECHA_PROGRAMADA', etiqueta: 'k.FECHA_PROGRAMADA', tipo: 'fecha', obligatorio: true },
        { nombre: 'ID_CAMION', etiqueta: 'k.ID_CAMION', tipo: 'select', opciones: opcionesDeCamiones, obligatorio: true },
        { nombre: 'CEDULA_OPERARIO', etiqueta: 'k.CEDULA_OPERARIO', obligatorio: true },
        { nombre: 'NOTAS', etiqueta: 'k.NOTAS', tipo: 'textoLargo' }
      ],
      enviar: function (valores) {
        valores.ID_TRAYECTO = Number(valores.ID_TRAYECTO);
        valores.ID_CAMION = Number(valores.ID_CAMION);
        return Api.llamarComoRol('POST', '/ejecucion/aprobar', { EJECUCION: valores });
      },
      alTerminar: cargarEjecuciones
    });
  }

  // Envía una acción sobre una ejecución (iniciar, pausar, etc.).
  function enviarAccion(accion, ejecucion, datosExtra) {
    const datos = { ID: ejecucion.ID };
    if (datosExtra) {
      Object.assign(datos, datosExtra);
    }
    return Api.llamarComoRol('POST', '/ejecucion/' + accion, { EJECUCION: datos });
  }

  // Acciones simples: se envían directo y se recarga la tabla.
  async function ejecutarAccionDirecta(accion, ejecucion) {
    const respuesta = await ejecutarYAvisar(enviarAccion(accion, ejecucion));
    if (respuesta.exito) {
      cargarEjecuciones();
      // Al iniciar o reanudar, el operador pasa directo a la pantalla de ruta.
      if (accion === 'iniciar' || accion === 'reanudar') {
        abrirPantallaDeRuta(ejecucion, 'en_curso');
      }
    }
  }

  // Carga un archivo JavaScript y espera a que esté listo.
  function cargarScript(url) {
    return new Promise(function (alTerminar, alFallar) {
      const script = document.createElement('script');
      script.src = url;
      script.onload = function () { alTerminar(); };
      script.onerror = function () { alFallar(new Error('No se pudo cargar ' + url)); };
      document.head.append(script);
    });
  }

  // Pantalla de ruta del camionero (mapa, próxima parada, acciones). Ver modo-ruta.js.
  // Si la página no incluyó modo-ruta.js, se carga acá la primera vez que hace falta.
  async function abrirPantallaDeRuta(ejecucion, estado) {
    if (typeof ModoRuta === 'undefined') {
      try {
        await cargarScript(RAIZ + 'js/modo-ruta.js');
      } catch (error) {
        console.error(error);
        mostrarAviso(traducir('err.generic'), 'error');
        return;
      }
    }
    const copia = Object.assign({}, ejecucion, { ESTADO_EJECUCION: estado });
    ModoRuta.abrir(copia, cargarEjecuciones);
  }

  // Cancelar y rechazar piden una nota opcional.
  function abrirFormularioConNotas(accion, claveTitulo, ejecucion) {
    abrirFormulario({
      titulo: traducir(claveTitulo),
      campos: [{ nombre: 'NOTAS', etiqueta: 'k.NOTAS', tipo: 'textoLargo' }],
      enviar: function (valores) {
        return enviarAccion(accion, ejecucion, valores);
      },
      alTerminar: cargarEjecuciones
    });
  }

  async function abrirFormularioDeTerminar(ejecucion) {
    const destino = await datosDeDestinoDeEjecucion(ejecucion.ID);
    const posibles = destino === null ? [] : destino.seleccionados;
    const campos = [
      { nombre: 'LLENADO_CAMION', etiqueta: 'k.LLENADO_CAMION', tipo: 'numero', min: 0, max: 10, obligatorio: true, ayuda: 'hint.llenadoCamion' }
    ];
    if (posibles.length > 1) {
      campos.push({
        nombre: 'ID_ALMACENAMIENTO', etiqueta: 'k.ID_ALMACENAMIENTO', tipo: 'select', obligatorio: true,
        opciones: posibles.map(function (a) {
          return { valor: a.ID, texto: '#' + a.ID + ' · ' + convertirALista(a.TIPO_BASURAS).join(', ') + ' · ' + (a.VOLUMEN_ACTUAL || 0) + '/' + a.CANTIDAD + ' L' };
        })
      });
    }
    abrirFormulario({
      titulo: traducir('a.terminar'),
      campos: campos,
      enviar: function (valores) {
        const extra = { LLENADO_CAMION: Number(valores.LLENADO_CAMION) };
        if (posibles.length === 1) { extra.ID_ALMACENAMIENTO = Number(posibles[0].ID); }
        if (valores.ID_ALMACENAMIENTO !== undefined) { extra.ID_ALMACENAMIENTO = Number(valores.ID_ALMACENAMIENTO); }
        return enviarAccion('terminar', ejecucion, extra);
      },
      alTerminar: cargarEjecuciones
    });
  }

  // Decide qué botones mostrar según el estado de la ejecución y el rol de la persona.
  function crearBotonesDeFila(ejecucion) {
    const estado = ejecucion.ESTADO_EJECUCION;
    const botones = [];

    botones.push(crearBoton('a.detalle', function () {
      Modal.abrir(traducir('tab.ejecuciones') + ' #' + ejecucion.ID, construirDetalle(ejecucion), { ancho: true });
    }));

    if (rolActualEs('O') && (estado === 'en_curso' || estado === 'pausada')) {
      botones.push(crearBoton('a.abrirruta', function () { abrirPantallaDeRuta(ejecucion, estado); }));
    }
    if (rolActualEs('O') && estado === 'aprobada') {
      botones.push(crearBoton('a.iniciar', function () { ejecutarAccionDirecta('iniciar', ejecucion); }));
    }
    if (rolActualEs('O') && estado === 'pausada') {
      botones.push(crearBoton('a.reanudar', function () { ejecutarAccionDirecta('reanudar', ejecucion); }));
    }
    if (rolActualEs('O') && estado === 'en_curso') {
      botones.push(crearBoton('a.pausar', function () { ejecutarAccionDirecta('pausar', ejecucion); }));
    }
    if (rolActualEs('O') && (estado === 'en_curso' || estado === 'pausada')) {
      botones.push(crearBoton('a.terminar', function () { abrirFormularioDeTerminar(ejecucion); }));
    }

    const estaAbierta = estado === 'aprobada' || estado === 'en_curso' || estado === 'pausada';
    if (rolActualEs('O') && estaAbierta) {
      botones.push(crearBoton('a.cancelar', function () { abrirFormularioConNotas('cancelar', 'a.cancelar', ejecucion); }, 'danger'));
    }
    if (rolActualEs('AO') && estado === 'aprobada') {
      botones.push(crearBoton('a.rechazar', function () { abrirFormularioConNotas('rechazar', 'a.rechazar', ejecucion); }, 'danger'));
    }
    return botones;
  }

  function crearEtiquetaDeEstado(ejecucion) {
    const estado = ejecucion.ESTADO_EJECUCION;
    let clase = 'tag';
    if (estado === 'cancelada' || estado === 'rechazada' || estado === 'vencida') {
      clase = 'tag bad';
    } else if (estado === 'finalizada') {
      clase = 'tag off';
    }
    return crearElemento('span', { clase: clase, texto: traducirValor(estado) });
  }

  const columnas = [
    { titulo: 'k.ID', valor: function (e) { return '#' + e.ID; } },
    { titulo: 'k.ID_TRAYECTO', valor: function (e) { return '#' + e.ID_TRAYECTO; } },
    { titulo: 'k.FECHA_PROGRAMADA', valor: function (e) { return e.FECHA_PROGRAMADA; } },
    { titulo: 'k.ESTADO_EJECUCION', valor: crearEtiquetaDeEstado },
    { titulo: 'k.CEDULA_OPERARIO', valor: function (e) { return e.CEDULA_OPERARIO; } },
    { titulo: 'k.ID_CAMION', valor: function (e) { return e.ID_CAMION ? '#' + e.ID_CAMION : '—'; } }
  ];

  async function cargarEjecuciones() {
    mostrarCargando(zonaTabla);

    const filtros = {};
    if (filtroEstado.value !== '') {
      filtros.ESTADO_EJECUCION = filtroEstado.value;
    }

    const respuesta = await Api.llamarComoRol('GET', '/ejecucion', filtros);
    if (!respuesta.exito) {
      mostrarMensajeDeError(zonaTabla, respuesta.mensaje);
      return;
    }
    zonaTabla.replaceChildren(construirTabla(columnas, respuesta.lista, crearBotonesDeFila));
  }

  return { mostrar: cargarEjecuciones };
}

VISTAS.ejecuciones = crearVistaEjecuciones;
