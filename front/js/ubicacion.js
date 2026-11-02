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
      const ajuste = document.createElement('style');
      ajuste.textContent = '.leaflet-container{z-index:0;font:inherit}';
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
  }
};
