/**
 * app.js
 * Arranca app.html: revisa que haya sesión, pinta la zona de sesión (chip
 * de rol + avatar + cerrar sesión), arma la barra de tabs según el rol
 * logueado y delega cada menú a su módulo correspondiente.
 */

(function arrancar() {
  if (!auth.estaLogueado()) {
    location.href = 'pages/login.html';
    return;
  }

  if (!auth.perfilCompleto()) {
    MenuCompletar.mostrar(arrancarApp);
    return;
  }

  arrancarApp();
})();

function arrancarApp() {
  pintarZonaSesion();

  const tipo = auth.tipoUsuario();

  const tabsComunes = [
    { texto: 'Mapa', idSeccion: 'seccion_mapa' },
    { texto: 'Mis datos', idSeccion: 'seccion_datos' }
  ];

  let tabsRol = [];

  if (tipo === 'admin sistema') {
    tabsRol = [
      { texto: 'Usuarios', idSeccion: 'seccion_usuarios' },
      { texto: 'Solicitudes', idSeccion: 'seccion_solicitudes' },
      { texto: 'Logs', idSeccion: 'seccion_logs' }
    ];
  }
  // TODO: tabs de "vecino", "operador camion", "admin operador" y
  // "admin general" cuando estén definidos sus menús.

  Tabs.armarBarraPrincipal([...tabsComunes, ...tabsRol]);

  MenuDatos.iniciar();

  if (tipo === 'admin sistema') {
    MenuAdminUsuarios.iniciar();
    MenuAdminSolicitudes.iniciar();
    MenuAdminLogs.iniciar();
  }
}

function pintarZonaSesion() {
  zona_sesion.replaceChildren();

  const chip = document.createElement('span');
  chip.className = 'chip-rol';
  chip.textContent = etiquetaTipoUsuario(auth.tipoUsuario());

  const avatar = document.createElement('div');
  avatar.className = 'avatar';
  avatar.textContent = String(auth.ci()).slice(-2);

  const botonSalir = document.createElement('button');
  botonSalir.type = 'button';
  botonSalir.className = 'btn-icono eliminar';
  botonSalir.textContent = 'Cerrar sesión';
  botonSalir.addEventListener('click', () => {
    auth.cerrarSesion();
    location.href = 'pages/login.html';
  });

  zona_sesion.append(chip, avatar, botonSalir);
}
