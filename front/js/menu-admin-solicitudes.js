/**
 * menu-admin-solicitudes.js
 * Única función: pintar y manejar el menú "Solicitud usuario" de admin
 * sistema (listar, filtrar por tipo, seleccionar varias con checkbox y
 * borrarlas, o borrar una sola).
 *
 * OJO: el mockup que me pasaste solo mostraba el botón "borrar" (rechazar).
 * La API también tiene un endpoint /user/adminsys/requests/accept
 * (RUTAS_API.REQUESTS_ACCEPT) para aprobar una solicitud, pero como no
 * apareció ningún botón de "aceptar" en el dibujo que me pasaste, no lo
 * agregué. Avisame si va y lo sumo.
 */

const LISTA_MAPEO_SOLICITUDES = {
  'Error C ApiAdminSys GetRequestsError': 'No se pudieron obtener las solicitudes.',
  'Error C ApiAdminSys RequestNotFound': 'La solicitud no existe.',
  'Error C ApiAdminSys DeleteRequestError': 'No se pudo borrar la solicitud.',
  'Error C ApiAdminSys AcceptSignUpException': 'No se pudo aprobar la solicitud.',
  'Error C ApiAdminSys MigrateUserError': 'No se pudo dar de alta al usuario aprobado.',
  'Error C ApiAdminSys InvalidTargetCi': 'La cédula indicada no es válida.'
};

const MenuAdminSolicitudes = {
  async iniciar() {
    this._llenarFiltro();
    solicitudes_filtro_tipo.addEventListener('change', () => this._cargar());
    solicitudes_borrar_seleccionados.addEventListener('click', () => this._borrarSeleccionados());
    solicitudes_aceptar_seleccionados.addEventListener('click', () => this._crearSeleccionados());
    await this._cargar();
  },

  _llenarFiltro() {
    solicitudes_filtro_tipo.replaceChildren();
    const todos = document.createElement('option');
    todos.value = '';
    todos.textContent = 'Todos';
    solicitudes_filtro_tipo.appendChild(todos);
    CONFIG.TIPOS_USUARIO.forEach(t => {
      const opcion = document.createElement('option');
      opcion.value = t.valor;
      opcion.textContent = t.etiqueta;
      solicitudes_filtro_tipo.appendChild(opcion);
    });
  },

  async _cargar() {
    solicitudes_tabla_cuerpo.replaceChildren();
    solicitudes_tabla_vacia.classList.add('hidden');

    const resultado = await ApiCliente.fetchDatos('REQUESTS', {
      TOKEN: auth.tokenUsuario(),
      TYPEUSER: solicitudes_filtro_tipo.value || undefined
    }, LISTA_MAPEO_SOLICITUDES);

    if (resultado.esError) {
      solicitudes_tabla_vacia.textContent = resultado.mensajeUsuario;
      solicitudes_tabla_vacia.classList.remove('hidden');
      return;
    }
    this._pintar(resultado.datos || []);
  },

  _pintar(lista) {
    solicitudes_tabla_cuerpo.replaceChildren();
    if (!lista.length) {
      solicitudes_tabla_vacia.textContent = 'No hay solicitudes pendientes.';
      solicitudes_tabla_vacia.classList.remove('hidden');
      return;
    }
    lista.forEach((solicitud) => {
      const fila = tpl_fila_solicitud.content.cloneNode(true);
      const checkbox = fila.querySelector('[data-campo="check"]');
      checkbox.dataset.ci = solicitud.CI;
      fila.querySelector('[data-campo="ci"]').textContent = solicitud.CI;
      fila.querySelector('[data-campo="tipo"]').textContent = etiquetaTipoUsuario(solicitud.TYPEUSER);
      fila.querySelector('[data-accion="borrar"]').addEventListener('click', () => this._borrarUna(solicitud.CI));
      fila.querySelector('[data-accion="aceptar"]').addEventListener('click', () => this._crearUna(solicitud.CI));

      solicitudes_tabla_cuerpo.appendChild(fila);
    });
  },

  async _crearUna(ci) {
    const confirmado = await Modal.confirmar('¿Seguro que querés aceptar/aprobar esta solicitud?');
    if (!confirmado) return;

    const resultado = await ApiCliente.fetchDatos('REQUESTS_ACCEPT', {
      TOKEN: auth.tokenUsuario(),
      USER: { CI: Number(ci) }
    }, LISTA_MAPEO_SOLICITUDES);

    if (resultado.esError) {
      solicitudes_tabla_vacia.textContent = resultado.mensajeUsuario;
      solicitudes_tabla_vacia.classList.remove('hidden');
      return;
    }

    await this._cargar();
  },

  async _borrarSeleccionados() {
    const seleccionados = [...solicitudes_tabla_cuerpo.querySelectorAll('[data-campo="check"]:checked')]
      .map(chk => chk.dataset.ci);

    if (!seleccionados.length) return;

    const confirmado = await Modal.confirmar('¿Seguro que querés borrar ' + seleccionados.length + ' solicitud(es)?');
    if (!confirmado) return;

    const resultados = await Promise.all(seleccionados.map(ci => ApiCliente.fetchDatos('REQUESTS_DELETE', {
      TOKEN: auth.tokenUsuario(),
      USER: { CI: Number(ci) }
    }, LISTA_MAPEO_SOLICITUDES)));

    const conError = resultados.find(r => r.esError);
    if (conError) {
      solicitudes_tabla_vacia.textContent = conError.mensajeUsuario;
      solicitudes_tabla_vacia.classList.remove('hidden');
    }

    await this._cargar();
    
    // Si la pestaña de usuarios ya está instanciada, la recargamos en segundo plano
    if (window.MenuAdminUsuarios) {
      MenuAdminUsuarios._cargar();
    }
  },

  async _crearSeleccionados() {

    const seleccionados = [...solicitudes_tabla_cuerpo.querySelectorAll('[data-campo="check"]:checked')]
      .map(chk => chk.dataset.ci);

    if (!seleccionados.length) return;

    const confirmado = await Modal.confirmar('¿Seguro que querés aceptar ' + seleccionados.length + ' solicitud(es)?');
    if (!confirmado) return;

    const resultados = await Promise.all(seleccionados.map(ci => ApiCliente.fetchDatos('REQUESTS_ACCEPT', {
      TOKEN: auth.tokenUsuario(),
      USER: { CI: Number(ci) }
    }, LISTA_MAPEO_SOLICITUDES)));

    const conError = resultados.find(r => r.esError);
    if (conError) {
      solicitudes_tabla_vacia.textContent = conError.mensajeUsuario;
      solicitudes_tabla_vacia.classList.remove('hidden');
    }

    await this._cargar();
    
    // Si la pestaña de usuarios ya está instanciada, la recargamos en segundo plano
    if (window.MenuAdminUsuarios) {
      MenuAdminUsuarios._cargar();
    }

    
  }

};
