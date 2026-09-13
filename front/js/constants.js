/**
 * constants.js
 * Única función: definir las rutas de la API.
 *
 * - URL_BASE: raíz absoluta del backend. Se suma obligatoriamente al
 *   principio de toda petición.
 * - RUTAS_API: diccionario clave -> { metodo, endpoint }, uno por cada
 *   endpoint documentado en la API (verbo HTTP + ruta relativa).
 * - URLS_API: la misma clave, pero ya resuelta a la URL completa
 *   (URL_BASE + endpoint), lista para usar sin volver a concatenar nada.
 */

const URL_BASE = 'http://localhost:8080/GIT/api';

const RUTAS_API = {
  // ---- Autenticación / usuario regular ----
  SIGN_IN:          { metodo: 'POST', endpoint: '/user/sign/in' },
  SIGN_UP:          { metodo: 'POST', endpoint: '/user/sign/up' },
  PROFILE_UPDATE:   { metodo: 'PUT',  endpoint: '/user/profile' },
  USER_COMPLETE:    { metodo: 'PUT',  endpoint: '/user/complete' },

  // ---- Admin sistema: usuarios ----
  USERS:            { metodo: 'GET',    endpoint: '/user/adminsys/users' },
  USER_EXISTS:      { metodo: 'GET',    endpoint: '/user/adminsys/user/exists' },
  USER_DATA:        { metodo: 'GET',    endpoint: '/user/adminsys/user/data' },
  USER_DATA_UPDATE: { metodo: 'PUT',    endpoint: '/user/adminsys/user/data' },
  USER_DELETE:      { metodo: 'DELETE', endpoint: '/user/adminsys/user' },

  // ---- Admin sistema: solicitudes ----
  REQUESTS:         { metodo: 'GET',    endpoint: '/user/adminsys/requests' },
  REQUESTS_EXISTS:  { metodo: 'GET',    endpoint: '/user/adminsys/requests/exists' },
  REQUESTS_ACCEPT:  { metodo: 'POST',   endpoint: '/user/adminsys/requests/accept' },
  REQUESTS_DELETE:  { metodo: 'DELETE', endpoint: '/user/adminsys/requests' },

  // ---- Admin sistema: logs ----
  LOGS_USER:        { metodo: 'GET', endpoint: '/user/adminsys/logs/user' },
  LOGS_USERS:       { metodo: 'GET', endpoint: '/user/adminsys/logs/users' },
  LOGS_SQL:         { metodo: 'GET', endpoint: '/user/adminsys/logs/sql' }
};

// Constante de cada ruta ya resuelta: URL_BASE + endpoint, una por cada
// clave de RUTAS_API (ej: URLS_API.SIGN_IN === 'http://localhost:8080/GIT/api/user/sign/in').
const URLS_API = Object.fromEntries(
  Object.entries(RUTAS_API).map(([clave, { endpoint }]) => [clave, URL_BASE + endpoint])
);
