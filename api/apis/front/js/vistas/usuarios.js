/*
  vistas/usuarios.js
  Solo admin sistema: lista de usuarios, edición de sus datos y borrado.
*/

function crearVistaUsuarios(seccion) {
  const filtroTipo = crearSelect(opcionesDeLista(TIPOS_USUARIO, 'all'));
  const filtroCedula = crearElemento('input', { clavePlaceholder: 'f.ci', inputmode: 'numeric' });
  const zonaTabla = crearElemento('div', {});

  // Lista de { cedula, tipo } tal como llegó del backend (el filtro por cédula se aplica en el navegador).
  let todosLosUsuarios = [];

  seccion.append(crearTituloDeSeccion('tab.usuarios'));
  seccion.append(crearElemento('div', { clase: 'filters' },
    crearFiltro('f.tipo', filtroTipo),
    crearFiltro('f.ci', filtroCedula)
  ));
  seccion.append(zonaTabla);

  filtroTipo.addEventListener('change', cargarUsuarios);
  filtroCedula.addEventListener('input', dibujarTabla);

  async function abrirOpcionesDelUsuario(usuario) {
    const respuesta = await Api.llamarComoRol('GET', '/user/data', { USER: { CI: Number(usuario.cedula) } });
    if (!respuesta.exito) {
      mostrarAviso(respuesta.mensaje, 'error');
      return;
    }

    let datos = respuesta.datos;
    if (respuesta.datos.USER) {
      datos = respuesta.datos.USER;
    }

    const campos = [];
    if (usuario.tipo !== 'vecino') {
      campos.push({ nombre: 'FIRSTNAME', etiqueta: 'k.FIRSTNAME', valor: datos.FIRSTNAME, mitad: true });
      campos.push({ nombre: 'LASTNAME', etiqueta: 'k.LASTNAME', valor: datos.LASTNAME, mitad: true });
    }
    campos.push({ nombre: 'PASSWORD', etiqueta: 'k.newpass', tipo: 'password', ayuda: 'ph.blank' });

    abrirFormulario({
      titulo: traducir('k.CI') + ' ' + usuario.cedula + ' · ' + traducirValor(usuario.tipo),
      campos: campos,
      enviar: function (valores) {
        if (Object.keys(valores).length === 0) {
          return respuestaSinCambios();
        }
        valores.CI = Number(usuario.cedula);
        return Api.llamarComoRol('PUT', '/user/data', { USER: valores });
      }
    });
  }

  async function borrarUsuario(usuario) {
    const resultado = await confirmarYEjecutar(traducir('q.borrar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('DELETE', '/user', { USER: { CI: Number(usuario.cedula) } }));
    });
    if (resultado !== null && resultado.exito) {
      cargarUsuarios();
    }
  }

  function crearBotonesDeFila(usuario) {
    const botones = [crearBoton('a.opciones', function () { abrirOpcionesDelUsuario(usuario); })];

    // Nadie puede borrarse a sí mismo.
    const esLaPersonaActual = String(usuario.cedula) === String(Sesion.cedula());
    if (!esLaPersonaActual) {
      botones.push(crearBoton('a.borrar', function () { borrarUsuario(usuario); }, 'danger'));
    }
    return botones;
  }

  const columnas = [
    { titulo: 'k.CI', valor: function (u) { return u.cedula; } },
    { titulo: 'k.TYPEUSER', valor: function (u) { return traducirValor(u.tipo); } }
  ];

  function dibujarTabla() {
    const textoBuscado = filtroCedula.value.trim();
    const usuariosVisibles = [];
    for (const usuario of todosLosUsuarios) {
      if (String(usuario.cedula).includes(textoBuscado)) {
        usuariosVisibles.push(usuario);
      }
    }
    zonaTabla.replaceChildren(construirTabla(columnas, usuariosVisibles, crearBotonesDeFila));
  }

  async function cargarUsuarios() {
    mostrarCargando(zonaTabla);

    const filtros = {};
    if (filtroTipo.value !== '') {
      filtros.TYPEUSER = filtroTipo.value;
    }

    const respuesta = await Api.llamarComoRol('GET', '/users', filtros);
    if (!respuesta.exito) {
      mostrarMensajeDeError(zonaTabla, respuesta.mensaje);
      return;
    }

    // El backend responde un objeto { "cédula": "tipo de usuario", ... }.
    todosLosUsuarios = [];
    for (const cedula of Object.keys(respuesta.datos)) {
      todosLosUsuarios.push({ cedula: cedula, tipo: respuesta.datos[cedula] });
    }
    dibujarTabla();
  }

  return { mostrar: cargarUsuarios };
}

VISTAS.usuarios = crearVistaUsuarios;
