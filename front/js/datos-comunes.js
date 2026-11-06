/*
  datos-comunes.js
  Consultas que se repiten en varias vistas.
*/

// Pide una lista al backend con el prefijo del rol actual. Si falla, devuelve una lista vacía.
async function pedirLista(ruta, filtros) {
  const respuesta = await Api.llamarComoRol('GET', ruta, filtros);
  if (respuesta.exito) {
    return respuesta.lista;
  }
  return [];
}

// Las siguientes funciones devuelven opciones { valor, texto } para listas desplegables.

async function opcionesDeContenedores() {
  const contenedores = await pedirLista('/contenedor');
  const opciones = [];
  for (const contenedor of contenedores) {
    opciones.push({ valor: contenedor.ID, texto: '#' + contenedor.ID + ' ' + (contenedor.UBICACION || '') });
  }
  return opciones;
}

async function opcionesDeCentros() {
  const centros = await pedirLista('/centro');
  const opciones = [];
  for (const centro of centros) {
    opciones.push({ valor: centro.ID, texto: '#' + centro.ID + ' ' + (centro.UBICACION || '') });
  }
  return opciones;
}

async function opcionesDeRutas() {
  const rutas = await pedirLista('/ruta');
  const opciones = [];
  for (const ruta of rutas) {
    opciones.push({ valor: ruta.ID, texto: '#' + ruta.ID });
  }
  return opciones;
}

async function opcionesDeCamiones() {
  const camiones = await pedirLista('/camion');
  const opciones = [];
  for (const camion of camiones) {
    opciones.push({ valor: camion.ID, texto: '#' + camion.ID + ' ' + (camion.MATRICULA || '') });
  }
  return opciones;
}

// Recupera los almacenamientos del centro final y prioriza los compatibles con el camión.
async function datosDeDestinoDeEjecucion(idEjecucion) {
  const respuestaEjecucion = await Api.llamarComoRol('GET', '/ejecucion/data', { EJECUCION: { ID: idEjecucion } });
  if (!respuestaEjecucion.exito) { return null; }
  const ejecucion = respuestaEjecucion.datos.EJECUCION || respuestaEjecucion.datos;
  const respuestaRuta = await Api.llamarComoRol('GET', '/ruta/data', { RUTA: { ID: Number(ejecucion.ID_RUTA) } });
  if (!respuestaRuta.exito) { return null; }
  const ruta = respuestaRuta.datos.RUTA || respuestaRuta.datos;
  if (!ruta.ID_CENTRO_FINAL) { return { almacenamientos: [], seleccionados: [] }; }

  const respuestaCentro = await Api.llamarComoRol('GET', '/centro/data', { CENTRO: { ID: Number(ruta.ID_CENTRO_FINAL) } });
  if (!respuestaCentro.exito) { return null; }
  const centro = respuestaCentro.datos.CENTRO || respuestaCentro.datos;
  const almacenamientos = convertirALista(centro.ALMACENAMIENTOS);
  const respuestaCamion = await Api.llamarComoRol('GET', '/camion/data', { CAMION: { ID: Number(ejecucion.ID_CAMION) } });
  const camion = respuestaCamion.exito ? (respuestaCamion.datos.CAMION || respuestaCamion.datos) : {};
  const tiposCamion = convertirALista(camion.TIPOS_BASURA);
  const compatibles = almacenamientos.filter(function (almacenamiento) {
    return convertirALista(almacenamiento.TIPO_BASURAS).some(function (tipo) { return tiposCamion.includes(tipo); });
  });
  return { almacenamientos: almacenamientos, seleccionados: compatibles.length > 0 ? compatibles : almacenamientos };
}

// El estado del camión llega como ESTADO_CAMION (o ESTADO en algunas respuestas).
function estadoDelCamion(camion) {
  if (camion.ESTADO_CAMION !== undefined) {
    return camion.ESTADO_CAMION;
  }
  return camion.ESTADO;
}

// Color del contenedor en el mapa y en las barras, según su nivel de llenado (0 a 11).
function colorSegunLlenado(llenado) {
  const nivel = Number(llenado || 0);
  if (nivel >= 11) {
    return '#7a1620';
  }
  if (nivel >= 8) {
    return '#c62f3a';
  }
  if (nivel >= 4) {
    return '#d99a1b';
  }
  return '#5f9a22';
}

function crearBarraDeLlenado(llenado) {
  const nivel = Number(llenado || 0);
  const porcentaje = (nivel / LLENADO_MAXIMO) * 100;
  const barra = crearBarra(porcentaje, colorSegunLlenado(nivel));
  barra.title = nivel + '/' + LLENADO_MAXIMO;
  return barra;
}
