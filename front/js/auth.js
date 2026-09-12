/**
 * auth.js
 * Única función: manejar la sesión del usuario logueado (guardarla, leerla,
 * cerrarla) y las dos acciones de autenticación (iniciar sesión / registrar
 * solicitud). Usa "api" para hablar con el servidor, pero no toca el DOM.
 */

class AuthManager {
  constructor(apiCliente) {
    this.api = apiCliente;
  }

  get _sesion() {
    const guardado = sessionStorage.getItem(CONFIG.STORAGE_KEY_SESION);
    return guardado ? JSON.parse(guardado) : null;
  }

  set _sesion(valor) {
    if (valor) {
      sessionStorage.setItem(CONFIG.STORAGE_KEY_SESION, JSON.stringify(valor));
    } else {
      sessionStorage.removeItem(CONFIG.STORAGE_KEY_SESION);
    }
  }

  estaLogueado() {
    return this._sesion !== null;
  }

  usuarioActual() {
    const s = this._sesion;
    return s ? s.usuario : null;
  }

  ci() {
    const u = this.usuarioActual();
    return u ? u.CI : null;
  }

  tipoUsuario() {
    const u = this.usuarioActual();
    return u ? u.TYPEUSER : null;
  }

  perfilCompleto() {
    const u = this.usuarioActual();
    return u ? Boolean(u.COMPLETEUSER) : false;
  }

  /**
   * Forma de TOKEN que piden los endpoints de admin sistema: solo la
   * cédula del admin logueado.
   */
  tokenAdmin() {
    return { CI: this.ci() };
  }

  /**
   * Forma de TOKEN que pide /user/profile: el usuario completo devuelto
   * por el login.
   */
  tokenUsuario() {
    return this.usuarioActual();
  }

  async iniciarSesion(ci, password) {
    const respuesta = await this.api.iniciarSesion(ci, password);
    const usuario = respuesta.TOKEN.USER;
    this._sesion = { usuario, firma: respuesta.TOKEN.SIGNATURE };
    return usuario;
  }

  registrarSolicitud(ci, password, tipoUsuario) {
    return this.api.registrarSolicitud(ci, password, tipoUsuario);
  }

  cerrarSesion() {
    this._sesion = null;
  }
}

const auth = new AuthManager(api);
