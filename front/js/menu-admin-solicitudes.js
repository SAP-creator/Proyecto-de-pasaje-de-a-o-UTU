/**
 * menu-admin-solicitudes.js
 * Única función: pintar y manejar el menú "Solicitud usuario" de admin
 * sistema (listar, filtrar por tipo, seleccionar varias con checkbox y
 * borrarlas, o borrar una sola).
 *
 * OJO: el mockup que me pasaste solo mostraba el botón "borrar" (rechazar).
 * La API también tiene un endpoint /user/adminsys/requests/accept para
 * aprobar una solicitud, pero como no apareció ningún botón de "aceptar" en
 * el dibujo que me pasaste, no lo agregué. Avisame si va y lo sumo.
 */

const MenuAdminSolicitudes = {
  async iniciar() {
    this._llenarFiltro();
    solicitudes_filtro_tipo.addEventListener('change', () => this._cargar());
    solicitudes_borrar_seleccionados.addEventListener('click', () => this._borrarSeleccionados());
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
    try {
      const lista = await api.obtenerSolicitudes(auth.tokenAdmin(), solicitudes_filtro_tipo.value);
      this._pintar(lista || []);
    } catch (error) {
      solicitudes_tabla_vacia.textContent = error.message || 'No se pudieron cargar las solicitudes.';
      solicitudes_tabla_vacia.classList.remove('hidden');
    }
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
      solicitudes_tabla_cuerpo.appendChild(fila);
    });
  },

  async _borrarUna(ci) {
    const confirmado = await Modal.confirmar('¿Seguro que querés borrar esta solicitud?');
    if (!confirmado) return;
    try {
      await api.rechazarSolicitud(auth.tokenAdmin(), ci);
      await this._cargar();
    } catch (error) {
      solicitudes_tabla_vacia.textContent = error.message;
      solicitudes_tabla_vacia.classList.remove('hidden');
    }
  },

  async _borrarSeleccionados() {
    const seleccionados = [...solicitudes_tabla_cuerpo.querySelectorAll('[data-campo="check"]:checked')]
      .map(chk => chk.dataset.ci);

    if (!seleccionados.length) return;

    const confirmado = await Modal.confirmar('¿Seguro que querés borrar ' + seleccionados.length + ' solicitud(es)?');
    if (!confirmado) return;

    try {
      await Promise.all(seleccionados.map(ci => api.rechazarSolicitud(auth.tokenAdmin(), ci)));
      await this._cargar();
    } catch (error) {
      solicitudes_tabla_vacia.textContent = error.message;
      solicitudes_tabla_vacia.classList.remove('hidden');
    }
  }
};
