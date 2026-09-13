/**
 * menu-datos.js
 * Única función: pintar y manejar el menú "Mis datos" (perfil propio),
 * compartido por todos los tipos de usuario.
 *
 * Por ahora deja tocar Nombre, Apellido y Contraseña para cualquier rol,
 * porque todavía no me pasaste qué campos puede editar cada TYPEUSER según
 * la documentación. Cuando me mandes esa parte, ajusto los permisos acá.
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
  },

  async _guardar() {
    const form = form_mis_datos;
    const cambios = {};
    /////modificar a futuro 
    if (form.FIRSTNAME.value.trim()) cambios.FIRSTNAME = form.FIRSTNAME.value.trim();
    if (form.LASTNAME.value.trim()) cambios.LASTNAME = form.LASTNAME.value.trim();
    if (form.PASSWORD.value) cambios.PASSWORD = form.PASSWORD.value;


    
    const resultado = await ApiCliente.fetchDatos('PROFILE_UPDATE', {
      TOKEN: auth.tokenUsuario() ,
      USER: cambios
    }, LISTA_MAPEO_DATOS);

    if (resultado.esError) {
      this._mostrarMensaje(resultado.mensajeUsuario, 'error');
      return;
    }

    // Si es un éxito pero no trajo mensaje o el traductor falló, forzamos el mensaje correcto
    let msjFinal = resultado.mensaje || resultado.mensajeUsuario;
    if (msjFinal === 'Error desconocido') {
      msjFinal = LISTA_MAPEO_DATOS['OK C ApiAdminSys UserDataUpdated'] || 'Datos actualizados correctamente.';
    }

    this._mostrarMensaje(msjFinal, 'exito');
    form.PASSWORD.value = '';
  },

  _mostrarMensaje(texto, tipo) {
    datos_mensaje.textContent = texto;
    datos_mensaje.classList.remove('hidden', 'error', 'exito');
    datos_mensaje.classList.add(tipo);
  }
};
