/**
 * api.js
 * Única función: comunicarse con la API. No sabe nada de sesión, ni de HTML,
 * ni de cómo se pinta la pantalla. Solo arma peticiones y devuelve datos.
 */

class ApiError extends Error {
  constructor(mensaje, status, cuerpo) {
    super(mensaje);
    this.name = 'ApiError';
    this.status = status;
    this.cuerpo = cuerpo;
  }
}

class ApiCliente {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
  }

  /**
   * Hace la petición HTTP de verdad.
   *
   * OJO: la documentación de la API pide mandar un "body" JSON incluso en los
   * métodos GET (TOKEN, USER, TYPEUSER, etc). Los navegadores, por spec de
   * fetch/XHR, NO permiten mandar body en peticiones GET/HEAD (lo descartan
   * solos). Por eso acá, para GET, los datos van como query string en un
   * parámetro "payload" en vez de ir en el body. Si el backend espera
   * recibirlos de otra forma para GET, avisame y lo ajustamos.
   */
  async _peticion(metodo, ruta, datos) {
    let url = this.baseUrl + ruta;
    const opciones = {
      method: metodo,
      headers: { 'Content-Type': 'application/json' }
    };

    if (metodo === 'GET' || metodo === 'HEAD') {
      if (datos) {
        url += '?payload=' + encodeURIComponent(JSON.stringify(datos));
      }
    } else if (datos) {
      opciones.body = JSON.stringify(datos);
    }

    let respuesta;
    try {
      respuesta = await fetch(url, opciones);
    } catch (errorRed) {
      throw new ApiError('No se pudo conectar con el servidor.', 0, null);
    }

    const tipoContenido = respuesta.headers.get('content-type') || '';
    let cuerpo = null;
    if (tipoContenido.includes('application/json')) {
      cuerpo = await respuesta.json().catch(() => null);
    } else {
      cuerpo = await respuesta.text().catch(() => null);
    }

    if (!respuesta.ok) {
      const mensaje = (cuerpo && cuerpo.mensaje) ? cuerpo.mensaje : 'Ocurrió un error al comunicarse con el servidor.';
      throw new ApiError(mensaje, respuesta.status, cuerpo);
    }

    return cuerpo;
  }

  // ---------------- Autenticación (POST) ----------------

  iniciarSesion(ci, password) {
    return this._peticion('POST', '/user/sign/in', {
      USER: { CI: Number(ci), PASSWORD: password }
    });
  }

  registrarSolicitud(ci, password, tipoUsuario) {
    return this._peticion('POST', '/user/sign/up', {
      USER: { CI: Number(ci), PASSWORD: password, TYPEUSER: tipoUsuario }
    });
  }

  // ---------------- Usuario regular (PUT) ----------------

  actualizarPerfilPropio(tokenUsuario, cambios) {
    return this._peticion('PUT', '/user/profile', {
      TOKEN: { USER: tokenUsuario },
      USER: cambios
    });
  }

  completarPerfil(ci, nombre, apellido) {
    return this._peticion('PUT', '/user/complete', {
      TOKEN: { CI: Number(ci) },
      USER: { FIRSTNAME: nombre, LASTNAME: apellido }
    });
  }

  // ---------------- Admin sistema: usuarios ----------------

  obtenerUsuarios(tokenAdmin, tipoUsuario) {
    return this._peticion('GET', '/user/adminsys/users', {
      TOKEN: tokenAdmin,
      TYPEUSER: tipoUsuario || undefined
    });
  }

  existeUsuario(tokenAdmin, ciConsultado, tipoUsuario) {
    return this._peticion('GET', '/user/adminsys/user/exists', {
      TOKEN: tokenAdmin,
      USER: { CI: Number(ciConsultado), TYPEUSER: tipoUsuario || undefined }
    });
  }

  obtenerDatosUsuario(tokenAdmin, ciConsultado) {
    return this._peticion('GET', '/user/adminsys/user/data', {
      TOKEN: tokenAdmin,
      USER: { CI: Number(ciConsultado) }
    });
  }

  modificarDatosUsuario(tokenAdmin, ciObjetivo, cambios) {
    return this._peticion('PUT', '/user/adminsys/user/data', {
      TOKEN: tokenAdmin,
      USER: { CI: Number(ciObjetivo), ...cambios }
    });
  }

  eliminarUsuario(tokenAdmin, ciObjetivo) {
    return this._peticion('DELETE', '/user/adminsys/user', {
      TOKEN: tokenAdmin,
      USER: { CI: Number(ciObjetivo) }
    });
  }

  // ---------------- Admin sistema: solicitudes ----------------

  obtenerSolicitudes(tokenAdmin, tipoUsuario) {
    return this._peticion('GET', '/user/adminsys/requests', {
      TOKEN: tokenAdmin,
      TYPEUSER: tipoUsuario || undefined
    });
  }

  existeSolicitud(tokenAdmin, ciConsultado, tipoUsuario) {
    return this._peticion('GET', '/user/adminsys/requests/exists', {
      TOKEN: tokenAdmin,
      USER: { CI: Number(ciConsultado), TYPEUSER: tipoUsuario || undefined }
    });
  }

  aceptarSolicitud(tokenAdmin, ciObjetivo, tipoUsuario) {
    return this._peticion('POST', '/user/adminsys/requests/accept', {
      TOKEN: tokenAdmin,
      USER: { CI: Number(ciObjetivo), TYPEUSER: tipoUsuario }
    });
  }

  rechazarSolicitud(tokenAdmin, ciObjetivo) {
    return this._peticion('DELETE', '/user/adminsys/requests', {
      TOKEN: tokenAdmin,
      USER: { CI: Number(ciObjetivo) }
    });
  }

  // ---------------- Admin sistema: logs ----------------

  obtenerLogsUsuario(tokenAdmin, ciConsultado, tipoLog) {
    return this._peticion('GET', '/user/adminsys/logs/user', {
      TOKEN: tokenAdmin,
      USER: { CI: Number(ciConsultado), TYPELOG: tipoLog || undefined }
    });
  }

  obtenerLogsUsuarios(tokenAdmin, tipoUsuario, tipoLog) {
    return this._peticion('GET', '/user/adminsys/logs/users', {
      TOKEN: tokenAdmin,
      TYPEUSER: tipoUsuario || undefined,
      TYPELOG: tipoLog || undefined
    });
  }

  obtenerLogsSql(tokenAdmin) {
    return this._peticion('GET', '/user/adminsys/logs/sql', {
      TOKEN: tokenAdmin
    });
  }
}

// Instancia única que usa el resto de la app.
const api = new ApiCliente(CONFIG.API_BASE_URL);
