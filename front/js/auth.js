/**
 * auth.js
 * Maneja la sesión del usuario logueado (guardar, leer, cerrar) y las
 * acciones de autenticación (iniciar sesión / registrar solicitud).
 */

const LISTA_MAPEO_AUTH = {
  'Error C ApiAdminSys NegativeCi': 'La cédula no puede ser negativa.',
  'Error C ApiAdminSys CiTooLong': 'La cédula ingresada es demasiado larga.',
  'Error C ApiAdminSys InvalidCiFormat': 'El formato de la cédula no es válido.',
  'Error C ApiAdminSys InvalidPasswordFormat': 'El formato de la contraseña no es válido.',
  'Error C ApiAdminSys InvalidUserPayload': 'Los datos ingresados no son válidos.',
  'Error C ApiAdminSys InvalidUserType': 'El tipo de usuario seleccionado no es válido.',
  'Error C ApiAdminSys UserNotFound': 'La cédula ingresada no está registrada.',
  'Error C ApiAdminSys StoredPassMissing': 'No pudimos validar tu contraseña. Contactá a un administrador.',
  'Error C ApiAdminSys WrongPassword': 'La contraseña ingresada es incorrecta.',
  'Error C ApiAdminSys SignInException': 'No se pudo iniciar sesión. Intentá de nuevo.',
  'Error C ApiAdminSys UserAlreadyExists': 'Ya existe una cuenta o solicitud con esa cédula.',
  'Error C ApiAdminSys SignUpException': 'No se pudo enviar la solicitud. Intentá de nuevo.',
  'OK C ApiAdminSys SignInOk': 'Sesión iniciada correctamente.',
  'OK C ApiAdminSys SignUpRequested': 'Solicitud enviada correctamente.'
};

const auth = {
  get _sesion() {
    const guardado = sessionStorage.getItem(CONFIG.STORAGE_KEY_SESION);
    return guardado ? JSON.parse(guardado) : null;
  },

  set _sesion(valor) {
    if (valor) {
      sessionStorage.setItem(CONFIG.STORAGE_KEY_SESION, JSON.stringify(valor));
    } else {
      sessionStorage.removeItem(CONFIG.STORAGE_KEY_SESION);
    }
  },

  estaLogueado() {
    return this._sesion !== null;
  },

  usuarioActual() {
    const s = this._sesion;
    return s ? s.usuario : null;
  },

  ci() {
    const u = this.usuarioActual();
    return u ? u.CI : null;
  },

  tipoUsuario() {
    const u = this.usuarioActual();
    return u ? u.TYPEUSER : null;
  },

  perfilCompleto() {
    const u = this.usuarioActual();
    return u ? Boolean(u.COMPLETEUSER) : false;
  },

  firma() {
    const s = this._sesion;
    return s ? s.firma : null;
  },

  /**
   * Forma de TOKEN que espera el backend: el token solo puede tener
   * exactamente CI, TYPEUSER y COMPLETEUSER (el backend rechaza cualquier
   * otro campo, de más o de menos), así que se arma explícitamente acá
   * en vez de reenviar el objeto de sesión tal cual.
   */
  tokenUsuario() {
    const u = this.usuarioActual() || {};
    return {
      USER: {
        CI: u.CI,
        TYPEUSER: u.TYPEUSER,
        COMPLETEUSER: Boolean(u.COMPLETEUSER)
      },
      SIGNATURE: this.firma()
    };
  },

  async iniciarSesion(ci, password) {
    const resultado = await ApiCliente.fetchDatos('SIGN_IN', {
      USER: { CI: Number(ci), PASSWORD: password }
    }, LISTA_MAPEO_AUTH);

    if (resultado.esError) throw new Error(resultado.mensajeUsuario);

    const token = resultado.TOKEN;
    if (!token || !token.USER) throw new Error('Error interno, intentalo más tarde.');

    this._sesion = { usuario: token.USER, firma: token.SIGNATURE };
    return token.USER;
  },

  /**
   * Se llama después de que /user/complete confirma que el perfil ya
   * quedó completo, para no depender de volver a loguearse.
   */
  marcarPerfilCompleto() {
    const sesion = this._sesion;
    if (!sesion) return;
    sesion.usuario.COMPLETEUSER = true;
    this._sesion = sesion;
  },

  async registrarSolicitud(ci, password, tipoUsuario) {
    const resultado = await ApiCliente.fetchDatos('SIGN_UP', {
      USER: { CI: Number(ci), PASSWORD: password, TYPEUSER: tipoUsuario }
    }, LISTA_MAPEO_AUTH);

    if (resultado.esError) throw new Error(resultado.mensajeUsuario);
    return resultado;
  },

  cerrarSesion() {
    this._sesion = null;
  }
};
