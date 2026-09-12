/**
 * menu-admin-usuarios.js
 * Única función: pintar y manejar el menú "Usuarios" de admin sistema
 * (listar, filtrar por tipo, buscar por CI, ver/editar datos de un usuario
 * puntual y borrarlo). Usa <template> del HTML, nunca arma HTML a mano.
 */

const MenuAdminUsuarios = {
  _usuarios: [],

  async iniciar() {
    this._llenarFiltro();
    usuarios_filtro_tipo.addEventListener('change', () => this._cargar());
    usuarios_buscar_ci.addEventListener('input', () => this._filtrarPorBusqueda());
    await this._cargar();
  },

  _llenarFiltro() {
    usuarios_filtro_tipo.replaceChildren();
    const todos = document.createElement('option');
    todos.value = '';
    todos.textContent = 'Todos';
    usuarios_filtro_tipo.appendChild(todos);
    CONFIG.TIPOS_USUARIO.forEach(t => {
      const opcion = document.createElement('option');
      opcion.value = t.valor;
      opcion.textContent = t.etiqueta;
      usuarios_filtro_tipo.appendChild(opcion);
    });
  },

  async _cargar() {
    usuarios_tabla_cuerpo.replaceChildren();
    usuarios_tabla_vacia.classList.add('hidden');
    try {
      const mapa = await api.obtenerUsuarios(auth.tokenAdmin(), usuarios_filtro_tipo.value);
      this._usuarios = Object.entries(mapa || {});
      this._pintar(this._usuarios);
    } catch (error) {
      this._mostrarErrorTabla(error.message);
    }
  },

  _filtrarPorBusqueda() {
    const texto = usuarios_buscar_ci.value.trim();
    const filtrados = texto
      ? this._usuarios.filter(([ci]) => String(ci).includes(texto))
      : this._usuarios;
    this._pintar(filtrados);
  },

  _pintar(lista) {
    usuarios_tabla_cuerpo.replaceChildren();
    if (!lista.length) {
      usuarios_tabla_vacia.textContent = 'No hay usuarios para mostrar.';
      usuarios_tabla_vacia.classList.remove('hidden');
      return;
    }
    usuarios_tabla_vacia.classList.add('hidden');
    lista.forEach(([ci, tipo]) => {
      const fila = tpl_fila_usuario.content.cloneNode(true);
      fila.querySelector('[data-campo="ci"]').textContent = ci;
      fila.querySelector('[data-campo="tipo"]').textContent = etiquetaTipoUsuario(tipo);
      fila.querySelector('[data-accion="opciones"]').addEventListener('click', () => this._abrirOpciones(ci));
      usuarios_tabla_cuerpo.appendChild(fila);
    });
  },

  _abrirOpciones(ci) {
    const contenedor = document.createElement('div');
    contenedor.appendChild(tpl_modal_opciones_usuario.content.cloneNode(true));

    contenedor.querySelector('[data-accion="ver-datos"]').addEventListener('click', () => this._abrirDatos(ci));
    contenedor.querySelector('[data-accion="borrar"]').addEventListener('click', () => this._borrar(ci));

    Modal.abrir('Opciones — CI ' + ci, contenedor);
  },

  async _abrirDatos(ci) {
    const contenedor = document.createElement('div');
    contenedor.appendChild(tpl_modal_datos_usuario.content.cloneNode(true));

    const form = contenedor.querySelector('form');
    const aviso = contenedor.querySelector('[data-campo="ci-tipo"]');
    const mensaje = contenedor.querySelector('[data-campo="mensaje"]');

    Modal.abrir('Datos del usuario', contenedor);

    try {
      const datos = await api.obtenerDatosUsuario(auth.tokenAdmin(), ci);
      aviso.textContent = 'CI ' + datos.CI + ' — ' + etiquetaTipoUsuario(datos.TYPEUSER);
      form.FIRSTNAME.value = datos.FIRSTNAME || '';
      form.LASTNAME.value = datos.LASTNAME || '';
    } catch (error) {
      this._mostrarMensajeForm(mensaje, error.message, 'error');
      return;
    }

    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      const cambios = {};
      if (form.FIRSTNAME.value.trim()) cambios.FIRSTNAME = form.FIRSTNAME.value.trim();
      if (form.LASTNAME.value.trim()) cambios.LASTNAME = form.LASTNAME.value.trim();
      if (form.PASSWORD.value) cambios.PASSWORD = form.PASSWORD.value;

      try {
        const respuesta = await api.modificarDatosUsuario(auth.tokenAdmin(), ci, cambios);
        this._mostrarMensajeForm(mensaje, respuesta.mensaje || 'Datos actualizados.', 'exito');
        form.PASSWORD.value = '';
      } catch (error) {
        this._mostrarMensajeForm(mensaje, error.message, 'error');
      }
    });
  },

  async _borrar(ci) {
    const confirmado = await Modal.confirmar('¿Seguro que querés borrar este usuario?');
    if (!confirmado) return;

    try {
      await api.eliminarUsuario(auth.tokenAdmin(), ci);
      Modal.cerrar();
      await this._cargar();
    } catch (error) {
      this._mostrarErrorTabla(error.message);
    }
  },

  _mostrarMensajeForm(elemento, texto, tipo) {
    elemento.textContent = texto;
    elemento.classList.remove('hidden', 'error', 'exito');
    elemento.classList.add(tipo);
  },

  _mostrarErrorTabla(texto) {
    usuarios_tabla_vacia.textContent = texto || 'No se pudo completar la operación.';
    usuarios_tabla_vacia.classList.remove('hidden');
  }
};
