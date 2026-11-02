/*
  api.js
  Cliente de la API del backend.

  El backend siempre responde con este formato:
    { "CODE": "OK C ApiAdminSys UsersFetched", "DATA": ... }
  donde DATA puede ser:
    - una lista (filas de una tabla)    -> respuesta.lista
    - un objeto (detalle, token, etc.)  -> respuesta.datos
    - nada (HTTP 204, sin cuerpo)

  Dónde viaja el token (depende del verbo HTTP):
    GET y DELETE -> header "X-Token" (el token en JSON y codificado en base64)
                    y los datos en la URL como ?payload=<JSON>
    POST y PUT   -> dentro del cuerpo JSON, bajo la clave "TOKEN"
*/

const Api = {

  // Llama a cualquier ruta de la API (ejemplo de ruta: "/user/sign/in").
  llamar: async function (metodo, ruta, datosAEnviar) {
    const token = Sesion.leerToken();
    const opciones = { method: metodo, headers: {} };
    let url = CONFIGURACION.URL_API + ruta;

    if (metodo === 'GET' || metodo === 'DELETE') {
      if (token !== null) {
        opciones.headers['X-Token'] = btoa(JSON.stringify(token));
      }
      const hayDatos = datosAEnviar && Object.keys(datosAEnviar).length > 0;
      if (hayDatos) {
        url = url + '?payload=' + encodeURIComponent(JSON.stringify(datosAEnviar));
      }
    } else {
      opciones.headers['Content-Type'] = 'application/json';
      const cuerpo = Object.assign({}, datosAEnviar);
      if (token !== null) {
        cuerpo.TOKEN = token;
      }
      opciones.body = JSON.stringify(cuerpo);
    }

    // Esta es la respuesta que devolvemos siempre, haya salido bien o mal.
    const respuesta = {
      exito: false,
      estadoHttp: 0,
      codigo: null,
      datos: {},
      lista: [],
      mensaje: ''
    };

    try {
      console.log(opciones);
      const respuestaHttp = await fetch(url, opciones);
      respuesta.estadoHttp = respuestaHttp.status;

      const textoDelCuerpo = await respuestaHttp.text();
      this.leerCuerpo(textoDelCuerpo, respuesta);

      const codigoIndicaError = respuesta.codigo !== null && !respuesta.codigo.startsWith('OK');
      respuesta.exito = respuestaHttp.ok && !codigoIndicaError;
    } catch (error) {
      console.error('[API] No se pudo conectar:', metodo, ruta, error);
      respuesta.codigo = 'Error U ApiCliente ConnectionFailed';
    }
    respuesta.mensaje = traducirCodigoDelServidor(respuesta.codigo, respuesta.exito, respuesta.estadoHttp);

    if (!respuesta.exito) {
      console.error('[API] ' + metodo + ' ' + ruta + ' -> ' + respuesta.estadoHttp + ' ' + respuesta.codigo);
    }

    const tokenInvalido = respuesta.estadoHttp === 401 && String(respuesta.codigo).includes('InvalidToken');
    if (tokenInvalido && Sesion.hayUsuarioIngresado()) {
      Sesion.cerrarSesion();
    }

    return respuesta;
  },

  // Igual que llamar(), pero antepone el prefijo del rol de la persona (por ejemplo "/user/vecino").
  llamarComoRol: function (metodo, ruta, datosAEnviar) {
    const prefijo = PREFIJO_API_POR_ROL[Sesion.tipo()];
    return this.llamar(metodo, prefijo + ruta, datosAEnviar);
  },

  // Pasa el texto JSON recibido a los campos "codigo", "datos" y "lista" de la respuesta.
  leerCuerpo: function (texto, respuesta) {
    if (texto === '') {
      return;
    }

    let cuerpo;
    try {
      cuerpo = JSON.parse(texto);
    } catch (error) {
      respuesta.codigo = 'Error U ApiCliente InvalidJson';
      return;
    }

    if (cuerpo === null || typeof cuerpo !== 'object') {
      return;
    }

    if (cuerpo.CODE) {
      respuesta.codigo = cuerpo.CODE;
    }

    if (Array.isArray(cuerpo.DATA)) {
      respuesta.lista = cuerpo.DATA;
    } else if (cuerpo.DATA !== null && typeof cuerpo.DATA === 'object') {
      respuesta.datos = cuerpo.DATA;
    }
  }
};
