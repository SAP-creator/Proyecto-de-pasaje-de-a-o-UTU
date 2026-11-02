/*
  vistas/mapa.js
  Mapa (Leaflet + OpenStreetMap) con los contenedores (y los centros de acopio, para quienes pueden verlos).
  Se puede elegir una ruta para dibujarla: centro de inicio -> contenedores en orden -> centro final.
*/

function crearVistaMapa(seccion) {
  const selectorDeRuta = crearElemento('select', {});
  const cajaDelMapa = crearElemento('div', { clase: 'map-box' });
  const cajaDeAviso = crearElemento('div', { clase: 'msg hidden' });

  // Solo operador, admin operador y admin general pueden consultar centros.
  const veCentros = rolActualEs('O', 'AO', 'AP');

  let mapa = null;
  let marcadores = [];
  let lineaDeRuta = null;
  let contenedores = [];
  let centros = [];

  const leyenda = crearElemento('div', { clase: 'legend' });
  const itemsDeLeyenda = [
    { color: '#5f9a22', clave: 'lg.ok' },
    { color: '#d99a1b', clave: 'lg.mid' },
    { color: '#c62f3a', clave: 'lg.high' },
    { color: '#7a1620', clave: 'lg.over' },
    { color: '#7b8680', clave: 'lg.off' }
  ];
  for (const item of itemsDeLeyenda) {
    leyenda.append(crearElemento('span', {}, crearElemento('i', { style: 'background:' + item.color }), traducir(item.clave)));
  }
  if (veCentros) {
    leyenda.append(crearElemento('span', {}, crearElemento('i', { style: 'background:#2f6f2a;border-radius:2px' }), traducir('lg.centro')));
  }

  seccion.append(crearTituloDeSeccion('map.title'));
  seccion.append(crearElemento('div', { clase: 'filters' }, crearFiltro('map.ruta', selectorDeRuta)));
  seccion.append(cajaDeAviso, cajaDelMapa, leyenda);

  selectorDeRuta.addEventListener('change', function () {
    dibujarRuta(selectorDeRuta.value);
  });

  // Agrega un marcador al mapa. Al hacer clic se abre una ventanita con "contenidoDeLaVentana".
  function agregarMarcador(posicion, color, esCuadrado, titulo, contenidoDeLaVentana) {
    const marcador = Mapas.crearMarcador(mapa, posicion, color, esCuadrado, titulo);
    marcador.bindPopup(contenidoDeLaVentana);
    marcadores.push(marcador);
  }

  function crearVentanaDeContenedor(contenedor) {
    const nivel = contenedor.LLENADO || 0;
    return crearElemento('div', { style: 'min-width:12rem' },
      crearElemento('strong', { texto: traducir('k.CONTENEDOR') + ' #' + contenedor.ID }),
      crearElemento('div', { texto: contenedor.UBICACION }),
      crearEtiquetas(contenedor.TIPO_BASURAS),
      crearElemento('div', { texto: traducir('k.LLENADO') + ': ' + nivel + '/' + LLENADO_MAXIMO }),
      crearBoton('a.detalle', function () { AccionesContenedor.abrirDetalle(contenedor.ID); }, 'btn sm')
    );
  }

  function colorDelContenedor(contenedor) {
    const estaEnFuncion = contenedor.EN_FUNCION === null || contenedor.EN_FUNCION === undefined || esVerdadero(contenedor.EN_FUNCION);
    if (!estaEnFuncion) {
      return '#7b8680';
    }
    return colorSegunLlenado(contenedor.LLENADO);
  }

  function ajustarVista(puntos) {
    if (puntos.length === 0) {
      return;
    }
    const limites = L.latLngBounds(puntos.map(function (p) { return [p.lat, p.lng]; }));
    mapa.fitBounds(limites, { padding: [30, 30] });
  }

  // Borra los marcadores anteriores y dibuja los de contenedores y centros.
  function dibujarMarcadores() {
    for (const marcador of marcadores) {
      marcador.remove();
    }
    marcadores = [];

    const todosLosPuntos = [];

    for (const contenedor of contenedores) {
      const posicion = leerUbicacion(contenedor.UBICACION);
      if (posicion === null) {
        continue;
      }
      todosLosPuntos.push(posicion);
      agregarMarcador(posicion, colorDelContenedor(contenedor), false, '#' + contenedor.ID, crearVentanaDeContenedor(contenedor));
    }

    for (const centro of centros) {
      const posicion = leerUbicacion(centro.UBICACION);
      if (posicion === null) {
        continue;
      }
      todosLosPuntos.push(posicion);
      const titulo = traducir('k.CENTRO') + ' #' + centro.ID;
      const ventana = crearElemento('div', {}, crearElemento('strong', { texto: titulo }), crearElemento('div', { texto: centro.UBICACION }));
      agregarMarcador(posicion, '#2f6f2a', true, titulo, ventana);
    }

    // Si no hay una ruta elegida, el mapa se ajusta para mostrar todos los puntos.
    if (selectorDeRuta.value === '') {
      ajustarVista(todosLosPuntos);
    }
  }

  function buscarUbicacionPorId(lista, id) {
    for (const elemento of lista) {
      if (String(elemento.ID) === String(id)) {
        return leerUbicacion(elemento.UBICACION);
      }
    }
    return null;
  }

  // Dibuja la línea de la ruta elegida. Si idRuta está vacío, solo borra la línea.
  async function dibujarRuta(idRuta) {
    if (lineaDeRuta !== null) {
      lineaDeRuta.remove();
      lineaDeRuta = null;
    }
    if (idRuta === '' || idRuta === undefined) {
      return;
    }

    const respuesta = await Api.llamarComoRol('GET', '/ruta/data', { RUTA: { ID: Number(idRuta) } });
    if (!respuesta.exito) {
      mostrarAviso(respuesta.mensaje, 'error');
      return;
    }

    let ruta = respuesta.datos;
    if (respuesta.datos.RUTA) {
      ruta = respuesta.datos.RUTA;
    }

    const puntos = [];

    const inicio = buscarUbicacionPorId(centros, ruta.ID_CENTRO_INICIO);
    if (inicio !== null) {
      puntos.push(inicio);
    }

    for (const elemento of convertirALista(ruta.CONTENEDORES)) {
      let idContenedor = elemento;
      if (typeof elemento === 'object' && elemento !== null) {
        idContenedor = elemento.ID_CONTENEDOR !== undefined ? elemento.ID_CONTENEDOR : elemento.ID;
      }
      const punto = buscarUbicacionPorId(contenedores, idContenedor);
      if (punto !== null) {
        puntos.push(punto);
      }
    }

    const final = buscarUbicacionPorId(centros, ruta.ID_CENTRO_FINAL);
    if (final !== null) {
      puntos.push(final);
    }

    if (puntos.length < 2) {
      mostrarAviso(traducir('map.noroute'));
      return;
    }

    lineaDeRuta = L.polyline(puntos.map(function (p) { return [p.lat, p.lng]; }), {
      color: '#2f6f2a',
      opacity: 0.9,
      weight: 4
    }).addTo(mapa);

    ajustarVista(puntos);
  }

  function llenarSelectorDeRuta(rutas, rutaElegida) {
    selectorDeRuta.replaceChildren();
    selectorDeRuta.append(crearOpcion('', traducir('none'), rutaElegida === ''));
    for (const ruta of rutas) {
      const texto = '#' + ruta.ID + ' (' + (ruta.CANTIDAD_CONTENEDORES || 0) + ')';
      selectorDeRuta.append(crearOpcion(ruta.ID, texto, String(ruta.ID) === String(rutaElegida)));
    }
  }

  // "parametros" puede traer { ruta: id } cuando se llega desde el botón "Ver en el mapa".
  async function mostrarMapa(parametros) {
    cajaDeAviso.classList.add('hidden');

    try {
      await Mapas.cargar();
    } catch (error) {
      cajaDeAviso.textContent = traducir('map.err');
      cajaDeAviso.className = 'msg error';
      return;
    }

    if (mapa === null) {
      mapa = Mapas.crear(cajaDelMapa, CONFIGURACION.CENTRO_DEL_MAPA, CONFIGURACION.ZOOM_DEL_MAPA);
    }
    // La pestaña pudo estar oculta al crearse el mapa: se recalcula su tamaño.
    mapa.invalidateSize();

    contenedores = await pedirLista('/contenedor');
    centros = [];
    if (veCentros) {
      centros = await pedirLista('/centro');
    }
    const rutas = await pedirLista('/ruta');

    let rutaElegida = selectorDeRuta.value;
    if (parametros && parametros.ruta) {
      rutaElegida = String(parametros.ruta);
    }
    llenarSelectorDeRuta(rutas, rutaElegida);

    dibujarMarcadores();
    await dibujarRuta(selectorDeRuta.value);
  }

  return { mostrar: mostrarMapa };
}

VISTAS.mapa = crearVistaMapa;
