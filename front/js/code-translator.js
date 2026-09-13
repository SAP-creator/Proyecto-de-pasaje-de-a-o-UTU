/**
 * code-translator.js
 * Única función: traducir los códigos de respuesta del backend.
 *
 * Formato del código: "[ESTADO] [CAPA] [SISTEMA] [MENSAJE]"
 * Ej: "Error C ApiAdminSys InvalidToken"
 */

class CodeTranslator {
  // Diccionario base, lo más chico posible: solo lo genérico que puede
  // pasarle a cualquier controlador. Cada pantalla le suma lo suyo a
  // través de "listaMapeo" en traducirUsuario().
  static MapaBasicoTraduccion = {
    'Error V ApiAdminSys MethodNotAllowed': 'Método no permitido.',
    'Error V ApiAdminSys PathNotFound': 'Ruta no encontrada.',
    'Error V ApiAdminSys InvalidJson': 'Los datos enviados no son válidos.',
    'Error V TEAPOT TEAPOT': 'Soy una tetera.',
    'Error C ApiAdminSys InvalidToken': 'Tu sesión no es válida. Iniciá sesión de nuevo.',
    'Error C ApiAdminSys AdminAccessDenied': 'No tenés permisos para hacer esto.',
    'Error U ApiCliente ConnectionFailed': 'No se pudo conectar con el servidor.'
  };

  static _CAPAS = { V: 'Vista', C: 'Controlador', M: 'Modelo', U: 'Utilidad' };

  static _partir(codigo) {
    if (!codigo || typeof codigo !== 'string') return null;
    const partes = codigo.trim().split(/\s+/);
    if (partes.length < 4) return null;
    const [estado, capa, sistema, ...resto] = partes;
    return { estado, capa, sistema, mensaje: resto.join(' ') };
  }

  /**
   * Traduce el código a un formato técnico, para mandar directo a la
   * consola de depuración.
   * Ej: "Error en Controlador llamado ApiAdminSys -- InvalidToken"
   */
  static traducirConsola(codigo) {
    const partido = this._partir(codigo);
    if (!partido) return codigo || 'Código de error desconocido';

    const estadoTexto = partido.estado === 'OK' ? 'Operación exitosa' : 'Error';
    const capaTexto = this._CAPAS[partido.capa] || partido.capa;

    return `${estadoTexto} en ${capaTexto} llamado ${partido.sistema} -- ${partido.mensaje}`;
  }

  /**
   * Indica si el código representa un error (todo lo que no empieza con
   * "OK"). Si el código no tiene el formato esperado, se asume error para
   * no dejar pasar por éxito algo que no pudimos interpretar.
   */
  static esError(codigo) {
    const partido = this._partir(codigo);
    if (!partido) return true;
    return partido.estado !== 'OK';
  }

  /**
   * Busca el código en MapaBasicoTraduccion combinado con listaMapeo
   * (el mapa propio del controlador que llama). Si no lo encuentra en
   * ninguno de los dos, retorna "OK" o "Error desconocido" según corresponda.
   */
  static traducirUsuario(codigo, listaMapeo) {
    if (!codigo || typeof codigo !== 'string') return 'Error desconocido';

    const mapaCompleto = { ...CodeTranslator.MapaBasicoTraduccion, ...(listaMapeo || {}) };
    if (mapaCompleto[codigo.trim()]) {
      return mapaCompleto[codigo.trim()];
    }

    const partido = this._partir(codigo);
    if (partido && partido.estado === 'OK') {
      return 'OK';
    }

    return 'Error desconocido';
  }
}