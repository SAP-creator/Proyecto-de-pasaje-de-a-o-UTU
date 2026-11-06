/*
  roles.js
  Qué pestañas ve cada tipo de usuario y cómo preguntar por el rol actual.

  Siglas usadas en todo el código:
    V  = vecino              O  = operador de camión
    AO = admin operador      AP = admin general (planificador)
    AS = admin sistema
*/

const NOMBRE_DE_ROL_POR_SIGLA = {
  'V': 'vecino',
  'O': 'operador camion',
  'AO': 'admin operador',
  'AP': 'admin general',
  'AS': 'admin sistema'
};

// Ejemplo: rolActualEs('AO', 'AP') es verdadero si la persona es admin operador o admin general.
function rolActualEs(...siglas) {
  const rolActual = Sesion.tipo();
  for (const sigla of siglas) {
    if (NOMBRE_DE_ROL_POR_SIGLA[sigla] === rolActual) {
      return true;
    }
  }
  return false;
}

// Pestañas que ve cada rol, en orden. Cada nombre corresponde a una vista en js/vistas/.
const PESTANAS_POR_ROL = {
  'vecino': ['mapa', 'datos', 'contenedores', 'incidentes', 'rutas'],
  'operador camion': ['mapa', 'datos', 'ejecuciones', 'contenedores', 'incidentes', 'centros', 'rutas', 'camiones', 'trayectos'],
  'admin operador': ['mapa', 'datos', 'contenedores', 'incidentes', 'centros', 'camiones', 'trayectos', 'ejecuciones', 'rutas'],
  'admin general': ['mapa', 'datos', 'contenedores', 'centros', 'rutas', 'incidentes', 'camiones', 'trayectos'],
  'admin sistema': ['mapa', 'datos', 'usuarios', 'solicitudes', 'logs', 'contenedores', 'incidentes', 'rutas']
};

// Cada archivo de js/vistas/ se registra acá: VISTAS.contenedores = crearVistaContenedores;
const VISTAS = {};
