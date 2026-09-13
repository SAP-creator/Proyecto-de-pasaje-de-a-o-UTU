class CodeTranslator {
  static STATUS_MAP = {
    'OK': 'Operación exitosa',
    'Error': 'Error'
  };

  static LAYER_MAP = {
    'V': 'Interfaz de usuario',
    'C': 'Controlador de lógica',
    'M': 'Base de datos / Modelo'
  };

  // Diccionario exclusivo para mensajes dirigidos al usuario final
  static MESSAGE_MAP = {
    'OK C Auth UserLoggedSuccessfully': 'Has iniciado sesión correctamente.',
    'Error M Billing DatabaseConnectionFailed': 'No pudimos procesar tu pago. Inténtalo más tarde.',
    'Error C User InvalidPassword': 'La contraseña ingresada es incorrecta.',
    'OK_Auth_UserNotFound': 'El usuario no se encuentra registrado.'
  };

  /**
   * Método para la interfaz de usuario (UI)
   * Devuelve solo el código original y el texto amigable.
   * @param {string} codeString 
   * 
   * Es la lista que de mensajes que puede traducir. 
   * Formatos
   *  - ESTADO_Controlador_NombreMensaje
   *  - ESTADO MVC Controlador NombreMensaje
   *   
   * 
   * @parm messageMap
   * @returns {{code: string, text: string}}
   */
  static translateForUser(codeString, messageMap) {
    if (!codeString || typeof codeString !== 'string') {
      return { code: codeString || '', text: 'Error desconocido' };
    }

    const cleanCode = codeString.trim();

    // 1. Busca coincidencia exacta del código completo en el diccionario
    if (messageMap[cleanCode]) {
      return { code: cleanCode, text: messageMap[cleanCode] };
    }

    const parts = cleanCode.split(/\s+/);
    if (parts.length >= 4) {
      const [status, layer, systemName, ...messageParts] = parts;
      const rawMessage = messageParts.join(' ');

      // 2. Busca por clave combinada "Sistema_Mensaje"
      const systemKey = `${systemName}_${rawMessage}`;
      if (messageMap[systemKey]) {
        return { code: cleanCode, text: messageMap[systemKey] };
      }

      // 3. Si no tiene traducción amigable, evalúa el estado base
      if (status === 'OK') {
        return { code: cleanCode, text: 'OK' };
      }
    }

    // Fallback cuando es un error no registrado en el diccionario
    return { code: cleanCode, text: 'Error desconocido' };
  }

  /**
   * Método para logs y depuración técnica en consola
   * @param {string} codeString 
   * @param {boolean} showLayer - Si añade la capa (V/C/M)
   * @param {boolean} showRaw - Si añade el mensaje técnico original
   * @returns {{code: string, text: string}}
   */
  static translateForConsole(codeString, showLayer = true, showRaw = true) {
    if (!codeString || typeof codeString !== 'string') {
      return { code: codeString || '', text: 'Error desconocido' };
    }

    const cleanCode = codeString.trim();
    const parts = cleanCode.split(/\s+/);

    if (parts.length < 4) {
      return { code: cleanCode, text: 'Error desconocido' };
    }

    const [status, layer, systemName, ...messageParts] = parts;
    const rawMessage = messageParts.join(' ');

    const statusText = this.STATUS_MAP[status];
    const layerText = this.LAYER_MAP[layer];

    if (!statusText || !layerText) {
      return { code: cleanCode, text: 'Error desconocido' };
    }

    let text = `${statusText} en ${systemName}`;

    if (showLayer) {
      text += ` (${layerText})`;
    }

    if (showRaw) {
      text += `: ${rawMessage}`;
    }

    return { code: cleanCode, text: text };
  }

  /**
   * 
   * @param {*} codeString 
   * 
   * @return True False o Null (fallo en la busqueda)
   */
  static is_errorCode(codeString){
    if (!codeString || typeof codeString !== 'string') 
      return null;
    

    if (codeString.includes('OK'))
      return false;
  
    if (codeString.includes('Error'))
      return true;

    return null;

  }
}