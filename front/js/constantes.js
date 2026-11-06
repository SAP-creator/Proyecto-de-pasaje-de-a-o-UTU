/*
  constantes.js
  Listas de valores permitidos. Deben coincidir con los enums del backend
  (api/constantes/Const_Sql.php).
*/

// Prefijo de la API para cada tipo de usuario (ver api/constantes/Const_Path.php).
const PREFIJO_API_POR_ROL = {
  'vecino': '/user/vecino',
  'operador camion': '/user/operador',
  'admin operador': '/user/adminoperador',
  'admin general': '/user/planificador',
  'admin sistema': '/user/adminsys'
};

const TIPOS_USUARIO = ['vecino', 'operador camion', 'admin operador', 'admin general', 'admin sistema'];

const TIPOS_BASURA = ['Plástico', 'Vidrio', 'Papel y cartón', 'Metales', 'Biodegradable'];
const CONDICIONES_CONTENEDOR = ['Normal', 'Vandalizado', 'Roto', 'Quemado', 'Reparacion'];
const ESTADOS_BASURA_CONTENEDOR = ['Basura_afuera', 'Limpio'];

const GRAVEDADES_INCIDENTE = ['Grave', 'Moderado', 'Leve'];
const ESTADOS_INCIDENTE = ['Pendiente', 'En proceso', 'Terminado'];

const DIAS_SEMANA = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
const ESTADOS_CAMION = ['disponible', 'no disponible', 'en trayecto'];
const ESTADOS_EJECUCION = ['aprobada', 'en_curso', 'pausada', 'finalizada', 'cancelada', 'rechazada', 'vencida'];

// El llenado de un contenedor o de un camión es un entero de 0 a 11.
const LLENADO_MINIMO = 0;
const LLENADO_MAXIMO = 11;
