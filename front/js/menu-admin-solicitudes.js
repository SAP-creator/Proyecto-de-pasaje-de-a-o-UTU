/**
 * menu-admin-solicitudes.js
 * Pinta y maneja el menú "Solicitud usuario" de admin sistema: listar,
 * filtrar por tipo, aceptar o borrar una solicitud puntual, y aceptar o
 * borrar varias seleccionadas con checkbox.
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
    solicitudes_borrar_seleccionados.addEventListener('click', () => this._accionSeleccionados('REQUESTS_DELETE', 'borrar'));
    solicitudes_aceptar_seleccionados.addEventListener('click', () => this._accionSeleccionados('REQUESTS_ACCEPT', 'aceptar'));
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

      const botonAceptar = fila.querySelector('[data-accion="aceptar"]');
      botonAceptar.classList.add('btn-exito');
      botonAceptar.addEventListener('click', () => this._accionUna(solicitud.CI, 'REQUESTS_ACCEPT', 'aceptar/aprobar'));

      fila.querySelector('[data-accion="borrar"]').addEventListener('click', () => this._accionUna(solicitud.CI, 'REQUESTS_DELETE', 'borrar'));

      solicitudes_tabla_cuerpo.appendChild(fila);
    });
  },

  async _accionUna(ci, urlKey, verbo) {
    const confirmado = await Modal.confirmar(`¿Seguro que querés ${verbo} esta solicitud?`);
    if (!confirmado) return;

    const resultado = await ApiCliente.fetchDatos(urlKey, {
      TOKEN: auth.tokenUsuario(),
      USER: { CI: Number(ci) }
    }, LISTA_MAPEO_SOLICITUDES);

    if (resultado.esError) {
      solicitudes_tabla_vacia.textContent = resultado.mensajeUsuario;
      solicitudes_tabla_vacia.classList.remove('hidden');
      return;
    }

    await this._cargar();
    if (window.MenuAdminUsuarios) MenuAdminUsuarios._cargar();
  },

  async _accionSeleccionados(urlKey, verbo) {
    const seleccionados = [...solicitudes_tabla_cuerpo.querySelectorAll('[data-campo="check"]:checked')]
      .map(chk => chk.dataset.ci);

    if (!seleccionados.length) return;

    const confirmado = await Modal.confirmar(`¿Seguro que querés ${verbo} ${seleccionados.length} solicitud(es)?`);
    if (!confirmado) return;

    const resultados = await Promise.all(seleccionados.map(ci => ApiCliente.fetchDatos(urlKey, {
      TOKEN: auth.tokenUsuario(),
      USER: { CI: Number(ci) }
    }, LISTA_MAPEO_SOLICITUDES)));

    const conError = resultados.find(r => r.esError);
    if (conError) {
      solicitudes_tabla_vacia.textContent = conError.mensajeUsuario;
      solicitudes_tabla_vacia.classList.remove('hidden');
    }

    await this._cargar();
    if (window.MenuAdminUsuarios) MenuAdminUsuarios._cargar();
  }
};
