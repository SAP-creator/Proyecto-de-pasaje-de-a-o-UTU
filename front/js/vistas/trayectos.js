/*
  vistas/trayectos.js
  Trayectos programados: qué ruta se recorre, a qué hora y qué día de la semana
  (o en una fecha especial). Solo el admin operador los gestiona.
*/

function crearVistaTrayectos(seccion) {
  const zonaTabla = crearElemento('div', {});
  const puedeGestionar = rolActualEs('AO');

  let botonNuevo = null;
  if (puedeGestionar) {
    botonNuevo = crearBoton('a.nuevo', function () { abrirFormularioDeTrayecto(null); }, 'btn pri');
  }
  seccion.append(crearTituloDeSeccion('tab.trayectos', botonNuevo));
  seccion.append(zonaTabla);

  async function abrirFormularioDeTrayecto(trayectoExistente) {
    const esNuevo = trayectoExistente === null;
    const actual = trayectoExistente || {};
    const rutas = await opcionesDeRutas();
    const camiones = await opcionesDeCamiones();

    const opcionesDeCamion = [{ valor: '', texto: traducir('none') }].concat(camiones);

    abrirFormulario({
      titulo: traducir(esNuevo ? 'a.nuevo' : 'a.editar'),
      campos: [
        { nombre: 'ID_RUTA', etiqueta: 'k.RUTA', tipo: 'select', opciones: rutas, valor: actual.ID_RUTA, obligatorio: true },
        { nombre: 'DIA_SEMANA', etiqueta: 'k.DIA_SEMANA', tipo: 'select', opciones: opcionesDeLista(DIAS_SEMANA, 'none'), valor: actual.DIA_SEMANA, mitad: true },
        { nombre: 'FECHA_ESPECIAL', etiqueta: 'k.FECHA_ESPECIAL', tipo: 'fecha', valor: actual.FECHA_ESPECIAL, mitad: true, ayuda: 'hint.diaofecha' },
        { nombre: 'HORA', etiqueta: 'k.HORA', ejemplo: '08:30:00', valor: actual.HORA, obligatorio: true, ayuda: 'hint.hora' },
        { nombre: 'INICIO_AUTOMATICO', etiqueta: 'k.INICIO_AUTOMATICO', tipo: 'casilla', valor: esVerdadero(actual.INICIO_AUTOMATICO), incluirSiVacio: true },
        { nombre: 'ID_CAMION_AUTO', etiqueta: 'k.ID_CAMION_AUTO', tipo: 'select', opciones: opcionesDeCamion, valor: actual.ID_CAMION_AUTO, mitad: true },
        { nombre: 'CEDULA_OPERARIO_AUTO', etiqueta: 'k.CEDULA_OPERARIO_AUTO', valor: actual.CEDULA_OPERARIO_AUTO, mitad: true }
      ],
      enviar: function (valores) {
        valores.ID_RUTA = Number(valores.ID_RUTA);
        if (valores.ID_CAMION_AUTO) {
          valores.ID_CAMION_AUTO = Number(valores.ID_CAMION_AUTO);
        }
        if (esNuevo) {
          return Api.llamarComoRol('POST', '/trayecto', { TRAYECTO: valores });
        }
        valores.ID = trayectoExistente.ID;
        return Api.llamarComoRol('PUT', '/trayecto', { TRAYECTO: valores });
      },
      alTerminar: cargarTrayectos
    });
  }

  async function borrarTrayecto(idTrayecto) {
    const resultado = await confirmarYEjecutar(traducir('q.borrar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('DELETE', '/trayecto', { TRAYECTO: { ID: idTrayecto } }));
    });
    if (resultado !== null && resultado.exito) {
      cargarTrayectos();
    }
  }

  function crearBotonesDeFila(trayecto) {
    return [
      crearBoton('a.editar', function () { abrirFormularioDeTrayecto(trayecto); }),
      crearBoton('a.borrar', function () { borrarTrayecto(trayecto.ID); }, 'danger')
    ];
  }

  // Un trayecto se repite un día de la semana o se hace una fecha especial; nunca ambos.
  function textoDelDia(trayecto) {
    if (trayecto.DIA_SEMANA) {
      return traducirValor(trayecto.DIA_SEMANA);
    }
    return trayecto.FECHA_ESPECIAL || '—';
  }

  const columnas = [
    { titulo: 'k.ID', valor: function (t) { return '#' + t.ID; } },
    { titulo: 'k.RUTA', valor: function (t) { return '#' + t.ID_RUTA; } },
    { titulo: 'k.DIA_SEMANA', valor: textoDelDia },
    { titulo: 'k.HORA', valor: function (t) { return t.HORA; } },
    {
      titulo: 'k.INICIO_AUTOMATICO',
      valor: function (t) {
        if (esVerdadero(t.INICIO_AUTOMATICO)) {
          return traducir('yes');
        }
        return traducir('no');
      }
    },
    {
      titulo: 'k.ID_CAMION_AUTO',
      valor: function (t) {
        if (t.ID_CAMION_AUTO) {
          return '#' + t.ID_CAMION_AUTO;
        }
        return '—';
      }
    },
    { titulo: 'k.CEDULA_OPERARIO_AUTO', valor: function (t) { return t.CEDULA_OPERARIO_AUTO || '—'; } }
  ];

  async function cargarTrayectos() {
    mostrarCargando(zonaTabla);
    const respuesta = await Api.llamarComoRol('GET', '/trayecto');
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

  return { mostrar: cargarTrayectos };
}

VISTAS.trayectos = crearVistaTrayectos;
