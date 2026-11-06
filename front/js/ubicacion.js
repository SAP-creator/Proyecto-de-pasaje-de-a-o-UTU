/*
  ubicacion.js
  Las ubicaciones se guardan en el backend como texto "latitud,longitud",
  por ejemplo "-34.901100,-56.164500". Este archivo las convierte y carga el mapa
  (Leaflet + OpenStreetMap, gratuito y sin clave de API).
*/

// Convierte "-34.9,-56.1" en { lat: -34.9, lng: -56.1 }. Si el texto no es válido, devuelve null.
function leerUbicacion(texto) {
  if (texto === null || texto === undefined) {
    return null;
  }

  const partes = String(texto).split(',');
  if (partes.length !== 2) {
    return null;
  }

  const latitud = parseFloat(partes[0]);
  const longitud = parseFloat(partes[1]);
  if (isNaN(latitud) || isNaN(longitud)) {
    return null;
  }

  const latitudValida = latitud >= -90 && latitud <= 90;
  const longitudValida = longitud >= -180 && longitud <= 180;
  if (!latitudValida || !longitudValida) {
    return null;
  }

  return { lat: latitud, lng: longitud };
}

// Convierte { lat, lng } en el texto que guarda el backend.
function formatearUbicacion(punto) {
  return punto.lat.toFixed(6) + ',' + punto.lng.toFixed(6);
}

const Mapas = {
  promesaDeCarga: null,

  // Carga Leaflet (CSS y JS) una sola vez. Devuelve una promesa que se cumple cuando está listo.
  cargar: function () {
    if (window.L && window.L.map) {
      return Promise.resolve();
    }
    if (this.promesaDeCarga !== null) {
      return this.promesaDeCarga;
    }

    const esta = this;
    this.promesaDeCarga = new Promise(function (alTerminar, alFallar) {
      const version = '1.9.4';

      const estilos = document.createElement('link');
      estilos.rel = 'stylesheet';
      estilos.href = 'https://unpkg.com/leaflet@' + version + '/dist/leaflet.css';
      document.head.append(estilos);

      // Evita que el mapa quede por encima de las ventanas (modales) de la aplicación.
      // También define el aspecto de las etiquetas de los puntos de una ruta:
      //   .etq-ruta          etiqueta de un contenedor (C1, C2...), borde redondeado
      //   .etq-ruta.extremo  salida / llegada (centros), borde punteado y cuadrado
      //   .etq-ruta.actual   la próxima parada en el modo ruta del camionero
      const ajuste = document.createElement('style');
      ajuste.textContent =
        '.leaflet-container{z-index:0;font:inherit}' +
        '.leaflet-tooltip.etq-ruta{background:#fff;color:#111;border:2px solid #111;border-radius:999px;' +
        'padding:1px 8px;font-weight:700;font-size:12px;line-height:1.35;white-space:nowrap;box-shadow:0 1px 3px rgba(0,0,0,.35)}' +
        '.leaflet-tooltip.etq-ruta:before{display:none}' +
        '.leaflet-tooltip.etq-ruta.extremo{border-style:dashed;border-radius:3px}' +
        '.leaflet-tooltip.etq-ruta.actual{background:#e8590c;border-color:#e8590c;color:#fff}';
      document.head.append(ajuste);

      const script = document.createElement('script');
      script.src = 'https://unpkg.com/leaflet@' + version + '/dist/leaflet.js';
      script.async = true;
      script.onload = function () {
        alTerminar();
      };
      script.onerror = function () {
        esta.promesaDeCarga = null;
        alFallar(new Error('No se pudo cargar el mapa'));
      };
      document.head.append(script);
    });

    return this.promesaDeCarga;
  },

  // Crea un mapa dentro de "contenedor" con las teselas configuradas en config.js.
  crear: function (contenedor, centro, zoom) {
    const mapa = L.map(contenedor).setView([centro.lat, centro.lng], zoom);
    L.tileLayer(CONFIGURACION.URL_TILES, {
      maxZoom: CONFIGURACION.ZOOM_MAXIMO_TILES,
      attribution: CONFIGURACION.ATRIBUCION_TILES
    }).addTo(mapa);
    return mapa;
  },

  // Marcador redondo (o cuadrado) de un color. Se agrega al mapa y se devuelve.
  crearMarcador: function (mapa, posicion, color, esCuadrado, titulo) {
    let marcador;
    if (esCuadrado) {
      const icono = L.divIcon({
        className: '',
        iconSize: [18, 18],
        iconAnchor: [9, 9],
        html: '<div style="width:18px;height:18px;box-sizing:border-box;background:' + color +
          ';border:2px solid #fff;border-radius:2px;box-shadow:0 0 2px rgba(0,0,0,.5)"></div>'
      });
      marcador = L.marker([posicion.lat, posicion.lng], { icon: icono, title: titulo });
    } else {
      marcador = L.circleMarker([posicion.lat, posicion.lng], {
        radius: 9,
        fillColor: color,
        fillOpacity: 1,
        color: '#ffffff',
        weight: 2
      });
      if (titulo) {
        marcador.bindTooltip(titulo);
      }
    }
    return marcador.addTo(mapa);
  },

  /*
    Etiquetas de los puntos de una ruta.
    Cada parada es un objeto { rol, id, pos, numero }:
      rol     'salida' (centro de inicio), 'contenedor' o 'llegada' (centro final)
      id      ID del centro o del contenedor
      pos     { lat, lng }
      numero  solo para contenedores: su lugar en la ruta (1, 2, 3...)
  */

  // Nombre corto del punto: "Salida", "C1", "C2"..., "Llegada".
  nombreCortoDeParada: function (parada) {
    if (parada.rol === 'salida') {
      return traducir('ruta.salida');
    }
    if (parada.rol === 'llegada') {
      return traducir('ruta.llegada');
    }
    return traducir('ruta.c') + parada.numero;
  },

  // Texto completo de la etiqueta: "Salida · Centro #3", "C1 · #12", "Llegada · Centro #5".
  textoDeParada: function (parada) {
    if (parada.rol === 'contenedor') {
      return Mapas.nombreCortoDeParada(parada) + ' · #' + parada.id;
    }
    return Mapas.nombreCortoDeParada(parada) + ' · ' + traducir('k.CENTRO') + ' #' + parada.id;
  },

  // Dibuja una etiqueta fija arriba de cada punto de la ruta.
  // Si varias paradas caen en el mismo lugar (por ejemplo, la salida y la llegada son el mismo
  // centro) se juntan en una sola etiqueta: "Salida · Centro #1 / Llegada · Centro #1".
  // "indiceResaltado" (opcional) es la parada que se pinta como "próxima".
  // Devuelve la lista de etiquetas, para borrarlas después con .remove().
  dibujarEtiquetasDeRuta: function (mapa, paradas, indiceResaltado) {
    const grupos = [];
    const gruposPorLugar = {};

    for (let i = 0; i < paradas.length; i++) {
      const parada = paradas[i];
      const clave = parada.pos.lat.toFixed(6) + ',' + parada.pos.lng.toFixed(6);
      let grupo = gruposPorLugar[clave];
      if (!grupo) {
        grupo = { pos: parada.pos, textos: [], extremo: false, resaltado: false };
        gruposPorLugar[clave] = grupo;
        grupos.push(grupo);
      }
      grupo.textos.push(Mapas.textoDeParada(parada));
      if (parada.rol !== 'contenedor') {
        grupo.extremo = true;
      }
      if (i === indiceResaltado) {
        grupo.resaltado = true;
      }
    }

    const etiquetas = [];
    for (const grupo of grupos) {
      let clases = 'etq-ruta';
      if (grupo.extremo) {
        clases += ' extremo';
      }
      if (grupo.resaltado) {
        clases += ' actual';
      }
      // Se usa textContent (no HTML) para que ningún dato del servidor se interprete como código.
      const contenido = document.createElement('span');
      contenido.textContent = grupo.textos.join(' / ');

      const etiqueta = L.tooltip({
        permanent: true,
        direction: 'top',
        offset: [0, grupo.resaltado ? -16 : -10],
        className: clases,
        interactive: false
      })
        .setLatLng([grupo.pos.lat, grupo.pos.lng])
        .setContent(contenido)
        .addTo(mapa);
      etiquetas.push(etiqueta);
    }
    return etiquetas;
  }
};
