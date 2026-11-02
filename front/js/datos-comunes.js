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
