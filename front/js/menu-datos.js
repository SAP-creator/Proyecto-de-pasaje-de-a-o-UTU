/**
 * menu-datos.js
 * Pinta y maneja el menú "Mis datos" (perfil propio), compartido por
 * todos los tipos de usuario.
 *
 * TODO: los permisos de edición por TYPEUSER todavía no están definidos
 * en la documentación; por ahora se dejan editables Nombre, Apellido y
 * Contraseña para cualquier rol.
 */

const LISTA_MAPEO_DATOS = {
  'Error C ApiAdminSys InvalidPasswordFormat': 'El formato de la contraseña no es válido.',
  'Error C ApiAdminSys InvalidMissingDataProvided': 'Los datos ingresados no son válidos.',
  'Error C ApiAdminSys IncompleteDataDbError': 'No se pudieron guardar los datos.',
  'OK C ApiAdminSys UserDataUpdated': 'Datos actualizados.'
};

const MenuDatos = {
  iniciar() {
    datos_ci_solo_lectura.value = auth.ci();
    datos_tipo_solo_lectura.value = etiquetaTipoUsuario(auth.tipoUsuario());
    datos_guardar.addEventListener('click', () => this._guardar());

    // No hay un endpoint que devuelva Nombre/Apellido del propio usuario
    // (solo CI/TYPEUSER/COMPLETEUSER viajan en el token), así que estos
    // campos no se pueden precargar todavía.
  },

  async _guardar() {
    const form = form_mis_datos;
    const cambios = {};

    if (form.FIRSTNAME.value.trim()) cambios.FIRSTNAME = form.FIRSTNAME.value.trim();
    if (form.LASTNAME.value.trim()) cambios.LASTNAME = form.LASTNAME.value.trim();
    if (form.PASSWORD.value) cambios.PASSWORD = form.PASSWORD.value;

    const resultado = await ApiCliente.fetchDatos('PROFILE_UPDATE', {
      TOKEN: auth.tokenUsuario(),
      USER: cambios
    }, LISTA_MAPEO_DATOS);

    if (resultado.esError) {
      this._mostrarMensaje(resultado.mensajeUsuario, 'error');
      return;
    }

    const msjFinal = resultado.mensaje && resultado.mensaje !== 'Error desconocido'
      ? resultado.mensaje
      : (LISTA_MAPEO_DATOS['OK C ApiAdminSys UserDataUpdated'] || 'Datos actualizados correctamente.');

    this._mostrarMensaje(msjFinal, 'exito');
    form.PASSWORD.value = '';
  },

  _mostrarMensaje(texto, tipo) {
    datos_mensaje.textContent = texto;
    datos_mensaje.classList.remove('hidden', 'error', 'exito');
    datos_mensaje.classList.add(tipo);
  }
};
