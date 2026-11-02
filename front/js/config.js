/*
  config.js
  Es el único archivo que hay que editar para desplegar el front.
*/

const CONFIGURACION = {
  // Mapa gratuito: Leaflet + OpenStreetMap. No necesita clave de API.
  // Servidor de teselas (las imágenes del mapa). Se puede cambiar por otro proveedor
  // compatible con el formato {z}/{x}/{y} sin tocar el resto del código.
  URL_TILES: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  ATRIBUCION_TILES: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  ZOOM_MAXIMO_TILES: 19,

  // Dónde se centra el mapa al abrirlo (Montevideo) y con qué zoom.
  CENTRO_DEL_MAPA: { lat: -34.9011, lng: -56.1645 },
  ZOOM_DEL_MAPA: 12,

  // Dirección base de la API. Es relativa para que funcione detrás de Nginx.
  URL_API: '/GIT/api'
};

/*
  RAIZ es la carpeta donde está el front, calculada desde la URL actual.
  Ejemplos:
    http://localhost:8080/index.html          -> RAIZ = "/"
    http://localhost:8080/pages/login.html    -> RAIZ = "/"
    http://localhost/proyecto/front/app.html  -> RAIZ = "/proyecto/front/"
  Se usa para armar enlaces a otras páginas e imágenes.
*/
function calcularRaiz() {
  const rutaActual = location.pathname;
  const posicionUltimaBarra = rutaActual.lastIndexOf('/');
  let carpeta = rutaActual.substring(0, posicionUltimaBarra + 1);

  // Las páginas dentro de "pages/" están un nivel más adentro.
  if (carpeta.endsWith('pages/')) {
    carpeta = carpeta.substring(0, carpeta.length - 'pages/'.length);
  }
  return carpeta;
}

const RAIZ = calcularRaiz();
