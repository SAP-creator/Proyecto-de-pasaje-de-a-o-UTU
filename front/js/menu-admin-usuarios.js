/**
 * menu-admin-usuarios.js
 * Pinta y maneja el menú "Usuarios" de admin sistema: listar, filtrar por
 * tipo, buscar por CI, ver/editar los datos de un usuario puntual y
 * borrarlo. Usa <template> del HTML, nunca arma HTML a mano.
 */

const LISTA_MAPEO_USUARIOS = {
  'Error C ApiAdminSys GetUsersError': 'No se pudo obtener la lista de usuarios.',
  'Error C ApiAdminSys GetUserDataError': 'No se pudieron obtener los datos del usuario.',
  'Error C ApiAdminSys DeleteUserError': 'No se pudo borrar el usuario.',
  'Error C ApiAdminSys UserNotFound': 'El usuario no existe.',
  'Error C ApiAdminSys InvalidTargetCi': 'La cédula indicada no es válida.',
  'Error C ApiAdminSys InvalidMissingDataProvided': 'Los datos ingresados no son válidos.',
  'Error C ApiAdminSys InvalidPasswordFormat': 'El formato de la contraseña no es válido.',
  'Error C ApiAdminSys AdminAccessDenied': 'No tenés permisos para hacer esto.'
};

// Nombre a mostrar cuando el usuario no tiene Nombre/Apellido cargado
// (vecinos, o cualquier perfil todavía incompleto).
const NOMBRE_NO_DISPONIBLE = '...';

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

    const payload = {
      TOKEN: auth.tokenUsuario(),
      TYPEUSER: usuarios_filtro_tipo.value || undefined
    };

    const resultado = await ApiCliente.fetchDatos('USERS', payload, LISTA_MAPEO_USUARIOS);

    if (resultado.esError) {
      this._mostrarErrorTabla(resultado.mensajeUsuario);
      return;
    }

    // El endpoint devuelve un objeto clave-valor (CI -> TYPEUSER).
    const { codigo, esError, mensajeUsuario, ...mapa } = resultado;
    const pares = Object.entries(mapa);

    this._usuarios = await Promise.all(pares.map(async ([ci, tipo]) => ({
      ci,
      tipo,
      nombre: await this._obtenerNombre(ci, tipo)
    })));

    this._pintar(this._usuarios);
  },

  /**
   * Trae Nombre + Apellido de un usuario puntual. Los vecinos no tienen
   * esos campos, y cualquier perfil incompleto puede no tenerlos
   * cargados todavía: en esos casos se muestra NOMBRE_NO_DISPONIBLE.
   */
  async _obtenerNombre(ci, tipo) {
    if (tipo === 'vecino') return NOMBRE_NO_DISPONIBLE;

    const datos = await ApiCliente.fetchDatos('USER_DATA', {
      TOKEN: auth.tokenUsuario(),
      USER: { CI: Number(ci) }
    }, LISTA_MAPEO_USUARIOS);

    if (datos.esError || (!datos.FIRSTNAME && !datos.LASTNAME)) {
      return NOMBRE_NO_DISPONIBLE;
    }

    return [datos.FIRSTNAME, datos.LASTNAME].filter(Boolean).join(' ');
  },

  _filtrarPorBusqueda() {
    const texto = usuarios_buscar_ci.value.trim();
    const filtrados = texto
      ? this._usuarios.filter(u => String(u.ci).includes(texto))
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
    lista.forEach(({ ci, tipo, nombre }) => {
      const fila = tpl_fila_usuario.content.cloneNode(true);
      fila.querySelector('[data-campo="ci"]').textContent = ci;
      fila.querySelector('[data-campo="tipo"]').textContent = etiquetaTipoUsuario(tipo);

      const celdaNombre = fila.querySelector('[data-campo="nombre"]');
      if (celdaNombre) celdaNombre.textContent = nombre;

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

    const datos = await ApiCliente.fetchDatos('USER_DATA', {
      TOKEN: auth.tokenUsuario(),
      USER: { CI: Number(ci) }
    }, LISTA_MAPEO_USUARIOS);

    if (datos.esError) {
      this._mostrarMensajeForm(mensaje, datos.mensajeUsuario, 'error');
      return;
    }

    aviso.textContent = 'CI ' + datos.CI + ' — ' + etiquetaTipoUsuario(datos.TYPEUSER);
    form.FIRSTNAME.value = datos.FIRSTNAME || '';
    form.LASTNAME.value = datos.LASTNAME || '';

    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();

      const cambios = {};
      if (form.FIRSTNAME.value.trim()) cambios.FIRSTNAME = form.FIRSTNAME.value.trim();
      if (form.LASTNAME.value.trim()) cambios.LASTNAME = form.LASTNAME.value.trim();
      if (form.PASSWORD.value) cambios.PASSWORD = form.PASSWORD.value;

      if (!Object.keys(cambios).length) {
        this._mostrarMensajeForm(mensaje, 'No hay cambios para guardar.', 'error');
        return;
      }

      const boton = form.querySelector('button[type="submit"]');
      boton.disabled = true;

      const resultado = await ApiCliente.fetchDatos('USER_DATA_UPDATE', {
        TOKEN: auth.tokenUsuario(),
        USER: { CI: Number(ci), ...cambios }
      }, LISTA_MAPEO_USUARIOS);

      boton.disabled = false;

      if (resultado.esError) {
        this._mostrarMensajeForm(mensaje, resultado.mensajeUsuario, 'error');
        return;
      }

      this._mostrarMensajeForm(mensaje, resultado.mensaje || resultado.mensajeUsuario, 'exito');
      form.PASSWORD.value = '';

      // Refresca la tabla de fondo para que el cambio (ej: nombre nuevo)
      // se vea sin tener que cerrar el modal y volver a entrar.
      this._cargar();
    });
  },

  async _borrar(ci) {
    const confirmado = await Modal.confirmar('¿Seguro que querés borrar este usuario?');
    if (!confirmado) return;

    const resultado = await ApiCliente.fetchDatos('USER_DELETE', {
      TOKEN: auth.tokenUsuario(),
      USER: { CI: Number(ci) }
    }, LISTA_MAPEO_USUARIOS);

    if (resultado.esError) {
      this._mostrarErrorTabla(resultado.mensajeUsuario);
      return;
    }

    Modal.cerrar();
    await this._cargar();
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
