/**
 * api.js
 * Única función: ejecutar peticiones contra la API a partir de las claves
 * definidas en RUTAS_API (constants.js), y traducir siempre la respuesta
 * con CodeTranslator antes de devolverla.
 */

const ApiCliente = {
  /**
   * @param {string} urlKey      Clave de RUTAS_API (ej: "SIGN_IN").
   * @param {object} [body]      Body a enviar (o payload en query si es GET).
   * @param {object} [listaMapeo] Traducciones propias del controlador que llama.
   * @returns {Promise<object>}  Todo el body devuelto por el backend, más
   *                             { codigo, esError, mensajeUsuario }.
   */
  async fetchDatos(urlKey, body, listaMapeo) {
    const ruta = RUTAS_API[urlKey];

    if (!ruta) {
      console.error(`[API] La clave "${urlKey}" no existe en RUTAS_API.`);
      return this._empaquetar(null, null, listaMapeo);
    }

    const opciones = {
      method: ruta.metodo,
      headers: { 'Content-Type': 'application/json' }
    };

    let url = URL_BASE + ruta.endpoint;
    if (ruta.metodo === 'GET' || ruta.metodo === 'HEAD' || ruta.metodo === 'DELETE') {
      if (body) url += '?payload=' + encodeURIComponent(JSON.stringify(body));
    } else if (body) {
      opciones.body = JSON.stringify(body);
    }

    let cuerpo = null;
    let codigo = null;

    try {

      const respuesta = await fetch(url, opciones);

      // Capturamos el texto crudo para ver qué carajo está respondiendo el server
      const textoCrudo = await respuesta.text();


      // Intentamos parsearlo a JSON a mano
      cuerpo = textoCrudo ? JSON.parse(textoCrudo) : null;
      codigo = this._extraerCodigo(cuerpo);
    } catch (errorRed) {
      console.error(`[API] Error parseando JSON en ${urlKey}:`, errorRed);
      codigo = 'Error U ApiCliente ConnectionFailed';
    }

    if (codigo && CodeTranslator.esError(codigo)) {
      console.error(`[API] ${ruta.metodo} ${ruta.endpoint} → ${CodeTranslator.traducirConsola(codigo)}`);
    }

    return this._empaquetar(cuerpo, codigo, listaMapeo);
  },

  /**
   * Arma el objeto de retorno: todo el body original del fetch (ya
   * aplanado, ver _normalizar), más el código crudo, si es error o no, y
   * la traducción para el usuario.
   */
  _empaquetar(cuerpo, codigo, listaMapeo) {
    return {
      ...this._normalizar(cuerpo),
      codigo,
      esError: codigo ? CodeTranslator.esError(codigo) : false,
      mensajeUsuario: CodeTranslator.traducirUsuario(codigo, listaMapeo)
    };
  },

  /**
   * El backend responde SIEMPRE con un array de "bloques": el primero
   * suele ser { CODE: "..." } y los siguientes son los datos en sí, que
   * pueden venir como objeto con claves con nombre (TOKEN, mensaje,
   * EXISTS, el mapa CI->TYPEUSER, etc.) o como una lista cruda (ej. en
   * /requests o en los logs). Esta función junta todo eso en un único
   * objeto plano, para que el resto del código pueda leer
   * "resultado.TOKEN" o "resultado.mensaje" directo, sin tener que andar
   * recorriendo el array a mano.
   *
   * - Bloques objeto: se combinan sus claves en el resultado (el CODE se
   *   descarta acá porque ya se extrajo aparte).
   * - Bloques que son ellos mismos un array (una lista de datos): se
   *   guardan en resultado.datos.
   */
  _normalizar(cuerpo) {
    if (Array.isArray(cuerpo)) {
      const resultado = {};
      for (const bloque of cuerpo) {
        if (Array.isArray(bloque)) {
          resultado.datos = bloque;
        } else if (bloque && typeof bloque === 'object') {
          for (const [clave, valor] of Object.entries(bloque)) {
            if (clave === 'CODE') continue;
            resultado[clave] = valor;
          }
        }
      }
      return resultado;
    }

    if (cuerpo && typeof cuerpo === 'object') {
      const { CODE, ...resto } = cuerpo;
      return resto;
    }

    return (cuerpo === null || cuerpo === undefined) ? {} : { datos: cuerpo };
  },

  /**
   * Busca recursivamente el atributo CODE dentro de la respuesta HTTP de PHP.
   */
  _extraerCodigo(cuerpo) {
    if (!cuerpo || typeof cuerpo !== 'object') return null;
    if (cuerpo.CODE) return cuerpo.CODE;

    if (Array.isArray(cuerpo)) {
      for (const item of cuerpo) {
        const encontrado = this._extraerCodigo(item);
        if (encontrado) return encontrado;
      }
    } else {
      for (const clave of Object.keys(cuerpo)) {
        if (typeof cuerpo[clave] === 'object' && cuerpo[clave] !== null) {
          const encontrado = this._extraerCodigo(cuerpo[clave]);
          if (encontrado) return encontrado;
        }
      }
    }

    return null;
  }
};
