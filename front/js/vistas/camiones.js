/*
  vistas/camiones.js
  Camiones de la flota. Todos los roles que la ven pueden listarla;
  solo el admin operador crea, edita y borra.
*/

function crearVistaCamiones(seccion) {
  const zonaTabla = crearElemento('div', {});
  const puedeGestionar = rolActualEs('AO');

  let botonNuevo = null;
  if (puedeGestionar) {
    botonNuevo = crearBoton('a.nuevo', function () { abrirFormularioDeCamion(null); }, 'btn pri');
  }
  seccion.append(crearTituloDeSeccion('tab.camiones', botonNuevo));
  seccion.append(zonaTabla);

  function abrirFormularioDeCamion(camionExistente) {
    const esNuevo = camionExistente === null;
    const actual = camionExistente || {};

    abrirFormulario({
      titulo: traducir(esNuevo ? 'a.nuevo' : 'a.editar'),
      campos: [
        { nombre: 'MATRICULA', etiqueta: 'k.MATRICULA', valor: actual.MATRICULA, obligatorio: true },
        { nombre: 'CANTIDAD', etiqueta: 'k.CANTIDAD', tipo: 'numero', min: 1, paso: 'any', valor: actual.CANTIDAD, obligatorio: true, ayuda: 'hint.litros' },
        { nombre: 'ESTADO_CAMION', etiqueta: 'k.ESTADO_CAMION', tipo: 'select', opciones: opcionesDeLista(ESTADOS_CAMION), valor: estadoDelCamion(actual) },
        { nombre: 'TIPO_BASURAS', etiqueta: 'k.TIPO_BASURAS', tipo: 'variasOpciones', opciones: opcionesDeLista(TIPOS_BASURA), valor: actual.TIPO_BASURAS, obligatorio: true }
      ],
      enviar: function (valores) {
        if (valores.CANTIDAD) {
          valores.CANTIDAD = Number(valores.CANTIDAD);
        }
        if (esNuevo) {
          return Api.llamarComoRol('POST', '/camion', { CAMION: valores });
        }
        valores.ID = camionExistente.ID;
        return Api.llamarComoRol('PUT', '/camion', { CAMION: valores });
      },
      alTerminar: cargarCamiones
    });
  }

  async function borrarCamion(idCamion) {
    const resultado = await confirmarYEjecutar(traducir('q.borrar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('DELETE', '/camion', { CAMION: { ID: idCamion } }));
    });
    if (resultado !== null && resultado.exito) {
      cargarCamiones();
    }
  }

  function crearBotonesDeFila(camion) {
    return [
      crearBoton('a.editar', function () { abrirFormularioDeCamion(camion); }),
      crearBoton('a.borrar', function () { borrarCamion(camion.ID); }, 'danger')
    ];
  }

  function crearEtiquetaDeEstado(camion) {
    const estado = estadoDelCamion(camion);
    let clase = 'tag warn';
    if (estado === 'disponible') {
      clase = 'tag';
    }
    return crearElemento('span', { clase: clase, texto: traducirValor(estado) });
  }

  const columnas = [
    { titulo: 'k.ID', valor: function (c) { return '#' + c.ID; } },
    { titulo: 'k.MATRICULA', valor: function (c) { return c.MATRICULA; } },
    { titulo: 'k.CANTIDAD', valor: function (c) { return c.CANTIDAD + ' L'; } },
    { titulo: 'k.TIPO_BASURAS', valor: function (c) { return crearEtiquetas(c.TIPO_BASURAS); } },
    { titulo: 'k.ESTADO_CAMION', valor: crearEtiquetaDeEstado }
  ];

  async function cargarCamiones() {
    mostrarCargando(zonaTabla);
    const respuesta = await Api.llamarComoRol('GET', '/camion');
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

  return { mostrar: cargarCamiones };
}

VISTAS.camiones = crearVistaCamiones;
