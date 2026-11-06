/*
  vistas/solicitudes.js
  Solo admin sistema: solicitudes de registro pendientes. Se aceptan o se borran.
*/

function crearVistaSolicitudes(seccion) {
  const filtroTipo = crearSelect(opcionesDeLista(TIPOS_USUARIO, 'all'));
  const filtroCedula = crearElemento('input', { clavePlaceholder: 'f.ci', inputmode: 'numeric' });
  const zonaTabla = crearElemento('div', {});
  let todasLasSolicitudes = [];

  seccion.append(crearTituloDeSeccion('tab.solicitudes'));
  seccion.append(crearElemento('div', { clase: 'filters' },
    crearFiltro('f.tipo', filtroTipo),
    crearFiltro('f.ci', filtroCedula)
  ));
  seccion.append(zonaTabla);

  filtroTipo.addEventListener('change', cargarSolicitudes);
  filtroCedula.addEventListener('input', dibujarTabla);

  async function aceptarSolicitud(solicitud) {
    const respuesta = await ejecutarYAvisar(Api.llamarComoRol('POST', '/requests/accept', { USER: { CI: Number(solicitud.CI) } }));
    if (respuesta.exito) {
      cargarSolicitudes();
    }
  }

  async function borrarSolicitud(solicitud) {
    const resultado = await confirmarYEjecutar(traducir('q.borrar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('DELETE', '/requests', { USER: { CI: Number(solicitud.CI) } }));
    });
    if (resultado !== null && resultado.exito) {
      cargarSolicitudes();
    }
  }

  function crearBotonesDeFila(solicitud) {
    return [
      crearBoton('a.aceptar', function () { aceptarSolicitud(solicitud); }),
      crearBoton('a.borrar', function () { borrarSolicitud(solicitud); }, 'danger')
    ];
  }

  const columnas = [
    { titulo: 'k.CI', valor: function (s) { return s.CI; } },
    { titulo: 'k.TYPEUSER', valor: function (s) { return traducirValor(s.TYPEUSER); } }
  ];

  function dibujarTabla() {
    const textoBuscado = filtroCedula.value.trim();
    const visibles = [];
    for (const solicitud of todasLasSolicitudes) {
      if (String(solicitud.CI).includes(textoBuscado)) {
        visibles.push(solicitud);
      }
    }
    zonaTabla.replaceChildren(construirTabla(columnas, visibles, crearBotonesDeFila));
  }

  async function cargarSolicitudes() {
    mostrarCargando(zonaTabla);

    const filtros = {};
    if (filtroTipo.value !== '') {
      filtros.TYPEUSER = filtroTipo.value;
    }

    const respuesta = await Api.llamarComoRol('GET', '/requests', filtros);
    if (!respuesta.exito) {
      mostrarMensajeDeError(zonaTabla, respuesta.mensaje);
      return;
    }
    todasLasSolicitudes = respuesta.lista;
    dibujarTabla();
  }

  return { mostrar: cargarSolicitudes };
}

VISTAS.solicitudes = crearVistaSolicitudes;
