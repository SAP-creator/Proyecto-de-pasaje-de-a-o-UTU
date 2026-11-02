/*
  sesion.js
  Guarda y lee la sesión (el token que devuelve el login) en sessionStorage.
  Se pierde al cerrar la pestaña del navegador.

  Forma del token:
    { USER: { CI: 123, TYPEUSER: "vecino", COMPLETEUSER: "s" }, SIGNATURE: "..." }
*/

const CLAVE_DE_SESION = 'sger_sesion';

const Sesion = {

  // Devuelve el token guardado o null si no hay sesión.
  leerToken: function () {
    const texto = sessionStorage.getItem(CLAVE_DE_SESION);
    if (texto === null) {
      return null;
    }
    try {
      return JSON.parse(texto);
    } catch (error) {
      return null;
    }
  },

  /*
    Guarda el token EXACTAMENTE como lo devolvió el backend.
    El backend firma los valores tal cual los emitió (por ejemplo COMPLETEUSER puede ser 1, "s" o true).
    Si se cambia el tipo de un valor (de 1 a true, de "123" a 123), la firma deja de coincidir
    y el backend responde 401 InvalidToken. Por eso acá no se convierte ni se reordena nada.
  */
  guardarToken: function (token) {
    sessionStorage.setItem(CLAVE_DE_SESION, JSON.stringify(token));
  },

  hayUsuarioIngresado: function () {
    return this.leerToken() !== null;
  },

  usuario: function () {
    const token = this.leerToken();
    if (token === null) {
      return null;
    }
    return token.USER;
  },

  cedula: function () {
    const usuario = this.usuario();
    if (usuario === null) {
      return null;
    }
    return usuario.CI;
  },

  tipo: function () {
    const usuario = this.usuario();
    if (usuario === null) {
      return null;
    }
    return usuario.TYPEUSER;
  },

  // El backend considera completo el perfil si COMPLETEUSER es true, 1 o "s".
  perfilCompleto: function () {
    const usuario = this.usuario();
    if (usuario === null) {
      return false;
    }
    const marca = usuario.COMPLETEUSER;
    return marca === true || marca === 1 || marca === '1' || marca === 's';
  },

  cerrarSesion: function () {
    sessionStorage.removeItem(CLAVE_DE_SESION);
    location.href = RAIZ + 'pages/login.html';
  }
};
