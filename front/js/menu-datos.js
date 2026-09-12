/**
 * menu-datos.js
 * Única función: pintar y manejar el menú "Mis datos" (perfil propio),
 * compartido por todos los tipos de usuario.
 *
 * Por ahora deja tocar Nombre, Apellido y Contraseña para cualquier rol,
 * porque todavía no me pasaste qué campos puede editar cada TYPEUSER según
 * la documentación. Cuando me mandes esa parte, ajusto los permisos acá.
 */

const MenuDatos = {
  iniciar() {
    datos_ci_solo_lectura.value = auth.ci();
    datos_tipo_solo_lectura.value = etiquetaTipoUsuario(auth.tipoUsuario());
    datos_guardar.addEventListener('click', () => this._guardar());
  },

  async _guardar() {
    const form = form_mis_datos;
    const cambios = {};
    if (form.FIRSTNAME.value.trim()) cambios.FIRSTNAME = form.FIRSTNAME.value.trim();
    if (form.LASTNAME.value.trim()) cambios.LASTNAME = form.LASTNAME.value.trim();
    if (form.PASSWORD.value) cambios.PASSWORD = form.PASSWORD.value;

    try {
      const respuesta = await api.actualizarPerfilPropio(auth.tokenUsuario(), cambios);
      this._mostrarMensaje(respuesta.mensaje || 'Datos actualizados.', 'exito');
      form.PASSWORD.value = '';
    } catch (error) {
      this._mostrarMensaje(error.message, 'error');
    }
  },

  _mostrarMensaje(texto, tipo) {
    datos_mensaje.textContent = texto;
    datos_mensaje.classList.remove('hidden', 'error', 'exito');
    datos_mensaje.classList.add(tipo);
  }
};
