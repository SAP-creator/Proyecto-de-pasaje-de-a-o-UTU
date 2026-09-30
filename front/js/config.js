/**
 * config.js
 * Configuración fija del sistema que no son rutas de la API (esas están
 * en constants.js).
 */

const CONFIG = {
  STORAGE_KEY_SESION: 'nombre_sesion',

  // Valores válidos del enum TYPEUSER (tienen que coincidir exactamente
  // con Const_Sql.php del backend).
  TIPOS_USUARIO: [
    { valor: 'vecino', etiqueta: 'Vecino' },
    { valor: 'operador camion', etiqueta: 'Camionero' },
    { valor: 'admin operador', etiqueta: 'Municipal · Operador' },
    { valor: 'admin general', etiqueta: 'Municipal · Planificador' },
    { valor: 'admin sistema', etiqueta: 'Admin sistema' }
  ]
};

function etiquetaTipoUsuario(valor) {
  const encontrado = CONFIG.TIPOS_USUARIO.find(t => t.valor === valor);
  return encontrado ? encontrado.etiqueta : (valor || '—');
}
