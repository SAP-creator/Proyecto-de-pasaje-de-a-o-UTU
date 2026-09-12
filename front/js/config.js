/**
 * config.js
 * Única función: guardar valores de configuración fijos del sistema.
 * No hace peticiones ni toca el DOM.
 */

const CONFIG = {
  // TODO: reemplazar por la URL real del backend cuando esté desplegado.
  API_BASE_URL: 'http://localhost:8080/GIT/api/',

  // Clave usada para guardar la sesión en sessionStorage.
  STORAGE_KEY_SESION: 'nombre_sesion',

  // Valores válidos del enum TYPEUSER, en el mismo orden que la documentación.
  TIPOS_USUARIO: [
    { valor: 'vecino', etiqueta: 'Vecino' },
    { valor: 'operador camion', etiqueta: 'Camionero' },
    { valor: 'admin operador', etiqueta: 'Municipal · Operador' },
    { valor: 'admin planificador', etiqueta: 'Municipal · Planificador' },
    { valor: 'admin sistema', etiqueta: 'Admin sistema' }
  ]
};

// Devuelve la etiqueta linda para mostrar a partir del valor crudo del enum.
function etiquetaTipoUsuario(valor) {
  const encontrado = CONFIG.TIPOS_USUARIO.find(t => t.valor === valor);
  return encontrado ? encontrado.etiqueta : (valor || '—');
}
