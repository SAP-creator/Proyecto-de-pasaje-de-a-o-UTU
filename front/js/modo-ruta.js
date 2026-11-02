/*
  modo-ruta.js
  Pantalla del operador de camión mientras recorre una ejecución.

  Se abre sola al iniciar o reanudar una ejecución, y con el botón "Abrir ruta" de la tabla.
  Muestra a pantalla completa:
    - El mapa con todos los contenedores (círculos) y centros de acopio (cuadrados).
    - La próxima parada resaltada con un color (colores.proxima); el resto, de otro (colores.otras).
    - La posición actual del camión por GPS (punto azul), si el navegador lo permite.
      Ojo: el GPS del navegador solo funciona en https:// o en localhost.
    - Flechas para ir de una parada a otra.
    - Si la parada es un contenedor: vaciado, llenado, estado e incidente (botones distintos).
    - Pausar / reanudar / terminar la ejecución.

  Las paradas salen de la ruta del trayecto: centro de inicio -> contenedores en orden -> centro final.
  La parada en la que quedó se recuerda en el navegador, para no perderla si se recarga la página.

  Uso:  ModoRuta.abrir(ejecucion, alCerrar)
    ejecucion   objeto con al menos ID y ESTADO_EJECUCION
    alCerrar    función opcional que se llama al salir (por ejemplo, recargar la tabla)
*/

const ModoRuta = {
  colores: {
    proxima: '#e8590c',
    otras: '#7b8680',
    yo: '#1a73e8',
    linea: '#2f6f2a'
  },
  estilosAgregados: false,

  agregarEstilos: function () {
    if (this.estilosAgregados) {
      return;
    }
    this.estilosAgregados = true;

    const estilos = document.createElement('style');
    estilos.textContent = `
      .ruta-ov{position:fixed;inset:0;z-index:40;background:#fff;display:flex;flex-direction:column}
      .ruta-h{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;padding:.55rem .9rem;border-bottom:1px solid var(--g200)}
      .ruta-h .grow{margin-right:auto}
      .ruta-cuerpo{flex:1;min-height:0;display:flex;flex-direction:column}
      .ruta-mapa{flex:1;min-height:0;background:var(--g100)}
      .ruta-panel{border-top:1px solid var(--g200);padding:.8rem .9rem;display:flex;flex-direction:column;gap:.6rem;max-height:48vh;overflow:auto}
      .ruta-parada{display:flex;flex-direction:column;gap:.6rem}
      .ruta-info{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap}
      .ruta-nav,.ruta-acc{display:flex;gap:.5rem;flex-wrap:wrap}
      .ruta-nav .btn{flex:1 1 7rem;padding:.8rem 1rem}
      .ruta-acc .btn{flex:1 1 9rem}
      .ruta-panel .legend{margin-top:0}
      @media(min-width:900px){
        .ruta-cuerpo{flex-direction:row}
        .ruta-panel{width:26rem;max-height:none;border-top:0;border-left:1px solid var(--g200)}
      }
    `;
    document.head.append(estilos);
  },

  // Distancia en línea recta entre dos puntos { lat, lng }, en metros.
  distanciaEnMetros: function (a, b) {
    const aRadianes = function (grados) { return grados * Math.PI / 180; };
    const dLat = aRadianes(b.lat - a.lat);
    const dLng = aRadianes(b.lng - a.lng);
    const h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(aRadianes(a.lat)) * Math.cos(aRadianes(b.lat)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * 6371000 * Math.asin(Math.sqrt(h));
  },

  textoDeDistancia: function (metros) {
    if (metros < 1000) {
      return Math.round(metros) + ' m';
    }
    return (metros / 1000).toFixed(1) + ' km';
  },

  abrir: async function (ejecucionInicial, alCerrar) {
    if (document.getElementById('modo-ruta')) {
      return;
    }
    ModoRuta.agregarEstilos();

    const colores = ModoRuta.colores;
    const idEjecucion = ejecucionInicial.ID;
    const claveDeGuardado = 'sger-ruta-' + idEjecucion;

    let estado = ejecucionInicial.ESTADO_EJECUCION;
    let cerrado = false;
    let mapa = null;
    let paradas = [];
    let contenedores = [];
    let centros = [];
    let indice = 0;
    let marcadores = [];
    let lineaDeRuta = null;
    let miPosicion = null;
    let marcadorYo = null;
    let circuloDePrecision = null;
    let idGps = null;
    let avisoDeGpsDado = false;
    let textoDistancia = null;

    // ---------- Estructura de la pantalla ----------

    const titulo = crearElemento('strong', { clase: 'grow', texto: traducir('tab.ejecuciones') + ' #' + idEjecucion });
    const etiquetaDeEstado = crearElemento('span', { clase: 'tag' });
    const botonPausar = crearBoton('a.pausar', function () { cambiarEstado('pausar', 'pausada'); }, 'btn sm');
    const botonReanudar = crearBoton('a.reanudar', function () { cambiarEstado('reanudar', 'en_curso'); }, 'btn sm');
    const botonTerminar = crearBoton('a.terminar', abrirFormularioDeTerminar, 'btn sm pri');
    const botonCerrar = crearElemento('button', { type: 'button', clase: 'btn sm ghost', alHacerClick: cerrar, 'aria-label': traducir('close') }, '✕');

    const cabecera = crearElemento('div', { clase: 'ruta-h' }, titulo, etiquetaDeEstado, botonPausar, botonReanudar, botonTerminar, botonCerrar);

    const cajaDelMapa = crearElemento('div', { clase: 'ruta-mapa' });
    const zonaDeParada = crearElemento('div', { clase: 'ruta-parada' });

    const leyenda = crearElemento('div', { clase: 'legend' });
    const itemsDeLeyenda = [
      { color: colores.proxima, clave: 'ruta.proxima' },
      { color: colores.otras, clave: 'ruta.otras' },
      { color: colores.yo, clave: 'ruta.yo' }
    ];
    for (const item of itemsDeLeyenda) {
      leyenda.append(crearElemento('span', {}, crearElemento('i', { style: 'background:' + item.color }), traducir(item.clave)));
    }

    const panel = crearElemento('div', { clase: 'ruta-panel' }, zonaDeParada, leyenda);
    const cuerpo = crearElemento('div', { clase: 'ruta-cuerpo' }, cajaDelMapa, panel);
    const caja = crearElemento('div', { clase: 'ruta-ov', role: 'dialog' }, cabecera, cuerpo);
    caja.id = 'modo-ruta';

    const overflowAnterior = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.body.append(caja);

    function alCambiarTamano() {
      if (mapa !== null) {
        mapa.invalidateSize();
      }
    }
    window.addEventListener('resize', alCambiarTamano);

    pintarEstado();
    zonaDeParada.append(crearElemento('div', { clase: 'empty', texto: traducir('loading') }));

    // ---------- Cerrar ----------

    function cerrar() {
      if (cerrado) {
        return;
      }
      cerrado = true;
      if (idGps !== null) {
        navigator.geolocation.clearWatch(idGps);
      }
      window.removeEventListener('resize', alCambiarTamano);
      if (mapa !== null) {
        mapa.remove();
      }
      caja.remove();
      document.body.style.overflow = overflowAnterior;
      if (alCerrar) {
        alCerrar();
      }
    }

    function mostrarError(texto) {
      zonaDeParada.replaceChildren(crearElemento('div', { clase: 'msg error', texto: texto }));
    }

    // ---------- Estado de la ejecución (pausar, reanudar, terminar) ----------

    function pintarEstado() {
      etiquetaDeEstado.textContent = traducirValor(estado);
      etiquetaDeEstado.className = estado === 'pausada' ? 'tag warn' : 'tag';
      botonPausar.classList.toggle('hidden', estado !== 'en_curso');
      botonReanudar.classList.toggle('hidden', estado !== 'pausada');
      botonTerminar.classList.toggle('hidden', estado !== 'en_curso' && estado !== 'pausada');
    }

    function enviarAccion(accion, datosExtra) {
      const datos = { ID: idEjecucion };
      if (datosExtra) {
        Object.assign(datos, datosExtra);
      }
      return Api.llamarComoRol('POST', '/ejecucion/' + accion, { EJECUCION: datos });
    }

    async function cambiarEstado(accion, estadoNuevo) {
      const respuesta = await ejecutarYAvisar(enviarAccion(accion));
      if (respuesta.exito) {
        estado = estadoNuevo;
        pintarEstado();
      }
    }

    function abrirFormularioDeTerminar() {
      abrirFormulario({
        titulo: traducir('a.terminar'),
        campos: [
          { nombre: 'LLENADO_CAMION', etiqueta: 'k.LLENADO_CAMION', tipo: 'numero', min: LLENADO_MINIMO, max: LLENADO_MAXIMO, ayuda: 'hint.llenado' }
        ],
        enviar: function (valores) {
          const extra = {};
          if (valores.LLENADO_CAMION !== undefined) {
            extra.LLENADO_CAMION = Number(valores.LLENADO_CAMION);
          }
          return enviarAccion('terminar', extra);
        },
        alTerminar: cerrar
      });
    }

    // ---------- Carga de datos ----------

    function buscarPorId(lista, id) {
      for (const elemento of lista) {
        if (String(elemento.ID) === String(id)) {
          return elemento;
        }
      }
      return null;
    }

    function agregarParada(tipo, id, lista) {
      const datos = buscarPorId(lista, id);
      if (datos === null) {
        return;
      }
      const posicion = leerUbicacion(datos.UBICACION);
      if (posicion === null) {
        return;
      }
      paradas.push({ tipo: tipo, id: datos.ID, pos: posicion, datos: datos });
    }

    // Busca la ruta de la ejecución (directo, o a través de su trayecto) y arma la lista de paradas.
    async function cargarDatos() {
      const respuestaEjecucion = await Api.llamarComoRol('GET', '/ejecucion/data', { EJECUCION: { ID: idEjecucion } });
      if (!respuestaEjecucion.exito) {
        throw new Error(respuestaEjecucion.mensaje);
      }
      const datosEjecucion = respuestaEjecucion.datos || {};
      const ejecucion = datosEjecucion.EJECUCION || datosEjecucion;
      if (ejecucion.ESTADO_EJECUCION) {
        estado = ejecucion.ESTADO_EJECUCION;
        pintarEstado();
      }

      let idRuta = ejecucion.ID_RUTA;
      if (!idRuta) {
        const idTrayecto = ejecucion.ID_TRAYECTO || ejecucionInicial.ID_TRAYECTO;
        const respuestaTrayecto = await Api.llamarComoRol('GET', '/trayecto/data', { TRAYECTO: { ID: Number(idTrayecto) } });
        if (!respuestaTrayecto.exito) {
          throw new Error(respuestaTrayecto.mensaje);
        }
        const datosTrayecto = respuestaTrayecto.datos || {};
        const trayecto = datosTrayecto.TRAYECTO || datosTrayecto;
        idRuta = trayecto.ID_RUTA;
      }

      const respuestaRuta = await Api.llamarComoRol('GET', '/ruta/data', { RUTA: { ID: Number(idRuta) } });
      if (!respuestaRuta.exito) {
        throw new Error(respuestaRuta.mensaje);
      }
      const datosRuta = respuestaRuta.datos || {};
      const ruta = datosRuta.RUTA || datosRuta;

      contenedores = await pedirLista('/contenedor');
      centros = await pedirLista('/centro');

      paradas = [];
      agregarParada('centro', ruta.ID_CENTRO_INICIO, centros);
      for (const elemento of convertirALista(ruta.CONTENEDORES)) {
        let idContenedor = elemento;
        if (typeof elemento === 'object' && elemento !== null) {
          idContenedor = elemento.ID_CONTENEDOR !== undefined ? elemento.ID_CONTENEDOR : elemento.ID;
        }
        agregarParada('contenedor', idContenedor, contenedores);
      }
      agregarParada('centro', ruta.ID_CENTRO_FINAL, centros);
    }

    // Vuelve a pedir los contenedores (por ejemplo, después de informar un llenado).
    async function recargarContenedores() {
      const lista = await pedirLista('/contenedor');
      if (lista.length > 0) {
        contenedores = lista;
        for (const parada of paradas) {
          if (parada.tipo === 'contenedor') {
            const nuevo = buscarPorId(contenedores, parada.id);
            if (nuevo !== null) {
              parada.datos = nuevo;
            }
          }
        }
      }
      if (mapa !== null) {
        dibujarMapa();
        pintarPanel();
      }
    }

    // ---------- Mapa ----------

    function crearMarcadorDeParada(posicion, esCuadrado, color, esGrande, titulo) {
      let marcador;
      if (esCuadrado) {
        const lado = esGrande ? 28 : 18;
        const borde = esGrande ? 3 : 2;
        const icono = L.divIcon({
          className: '',
          iconSize: [lado, lado],
          iconAnchor: [lado / 2, lado / 2],
          html: '<div style="width:' + lado + 'px;height:' + lado + 'px;box-sizing:border-box;background:' + color +
            ';border:' + borde + 'px solid #fff;border-radius:3px;box-shadow:0 0 3px rgba(0,0,0,.6)"></div>'
        });
        marcador = L.marker([posicion.lat, posicion.lng], { icon: icono, title: titulo, zIndexOffset: esGrande ? 1000 : 0 });
      } else {
        marcador = L.circleMarker([posicion.lat, posicion.lng], {
          radius: esGrande ? 15 : 9,
          fillColor: color,
          fillOpacity: 1,
          color: '#ffffff',
          weight: esGrande ? 3 : 2
        });
        marcador.bindTooltip(titulo);
      }
      return marcador.addTo(mapa);
    }

    function tituloDeParada(tipo, id) {
      if (tipo === 'centro') {
        return traducir('k.CENTRO') + ' #' + id;
      }
      return traducir('k.CONTENEDOR') + ' #' + id;
    }

    // Borra y vuelve a dibujar todos los marcadores: la próxima parada con su color y los demás con el otro.
    function dibujarMapa() {
      for (const marcador of marcadores) {
        marcador.remove();
      }
      marcadores = [];

      // Puntos que no son parte de la ruta (también se muestran, con el color común).
      const enLaRuta = {};
      for (const parada of paradas) {
        enLaRuta[parada.tipo + ':' + parada.id] = true;
      }
      for (const contenedor of contenedores) {
        const posicion = leerUbicacion(contenedor.UBICACION);
        if (posicion !== null && !enLaRuta['contenedor:' + contenedor.ID]) {
          marcadores.push(crearMarcadorDeParada(posicion, false, colores.otras, false, tituloDeParada('contenedor', contenedor.ID)));
        }
      }
      for (const centro of centros) {
        const posicion = leerUbicacion(centro.UBICACION);
        if (posicion !== null && !enLaRuta['centro:' + centro.ID]) {
          marcadores.push(crearMarcadorDeParada(posicion, true, colores.otras, false, tituloDeParada('centro', centro.ID)));
        }
      }

      // Paradas de la ruta: al tocar una, se salta a ella.
      for (let posicionEnRuta = 0; posicionEnRuta < paradas.length; posicionEnRuta++) {
        if (posicionEnRuta === indice) {
          continue;
        }
        marcadores.push(crearMarcadorDeParada(paradas[posicionEnRuta].pos, paradas[posicionEnRuta].tipo === 'centro', colores.otras, false, tituloDeParada(paradas[posicionEnRuta].tipo, paradas[posicionEnRuta].id)));
        const destino = posicionEnRuta;
        marcadores[marcadores.length - 1].on('click', function () { irA(destino); });
      }

      // La próxima parada se dibuja al final para que quede arriba de todo.
      const proxima = paradas[indice];
      marcadores.push(crearMarcadorDeParada(proxima.pos, proxima.tipo === 'centro', colores.proxima, true, tituloDeParada(proxima.tipo, proxima.id)));
    }

    function ajustarATodaLaRuta() {
      const puntos = [];
      for (const parada of paradas) {
        puntos.push([parada.pos.lat, parada.pos.lng]);
      }
      if (miPosicion !== null) {
        puntos.push([miPosicion.lat, miPosicion.lng]);
      }
      mapa.fitBounds(L.latLngBounds(puntos), { padding: [30, 30] });
    }

    function centrarEnMiPosicion() {
      if (miPosicion === null) {
        mostrarAviso(traducir('ruta.nogps'), 'error');
        return;
      }
      mapa.setView([miPosicion.lat, miPosicion.lng], Math.max(mapa.getZoom(), 16));
    }

    function guardarIndice() {
      try {
        localStorage.setItem(claveDeGuardado, String(indice));
      } catch (error) {
        // Sin almacenamiento del navegador simplemente no se recuerda la parada.
      }
    }

    function leerIndiceGuardado() {
      try {
        const guardado = parseInt(localStorage.getItem(claveDeGuardado), 10);
        if (!isNaN(guardado) && guardado >= 0 && guardado < paradas.length) {
          return guardado;
        }
      } catch (error) {
        // Ver arriba.
      }
      return null;
    }

    // Va a la parada número "nuevoIndice" (empezando de 0): la marca, la centra y muestra sus datos.
    function irA(nuevoIndice) {
      if (nuevoIndice < 0 || nuevoIndice >= paradas.length) {
        return;
      }
      indice = nuevoIndice;
      guardarIndice();
      dibujarMapa();
      pintarPanel();
      const posicion = paradas[indice].pos;
      mapa.setView([posicion.lat, posicion.lng], Math.max(mapa.getZoom(), 15));
    }

    // ---------- Posición actual (GPS) ----------

    function actualizarDistancia() {
      if (textoDistancia === null) {
        return;
      }
      if (miPosicion === null) {
        textoDistancia.textContent = '';
        return;
      }
      textoDistancia.textContent = '· ' + ModoRuta.textoDeDistancia(ModoRuta.distanciaEnMetros(miPosicion, paradas[indice].pos));
    }

    function iniciarGps() {
      if (!('geolocation' in navigator)) {
        return;
      }
      idGps = navigator.geolocation.watchPosition(function (resultado) {
        miPosicion = { lat: resultado.coords.latitude, lng: resultado.coords.longitude };
        const punto = [miPosicion.lat, miPosicion.lng];

        if (marcadorYo === null) {
          circuloDePrecision = L.circle(punto, { radius: resultado.coords.accuracy, color: colores.yo, weight: 1, fillOpacity: 0.1 }).addTo(mapa);
          marcadorYo = L.circleMarker(punto, { radius: 8, fillColor: colores.yo, fillOpacity: 1, color: '#ffffff', weight: 3 }).addTo(mapa);
        } else {
          marcadorYo.setLatLng(punto);
          circuloDePrecision.setLatLng(punto);
          circuloDePrecision.setRadius(resultado.coords.accuracy);
        }
        actualizarDistancia();
      }, function () {
        // Se avisa una sola vez; el resto de la pantalla funciona igual sin GPS.
        if (!avisoDeGpsDado) {
          avisoDeGpsDado = true;
          mostrarAviso(traducir('ruta.nogps'), 'error');
        }
      }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
    }

    // ---------- Panel de la parada actual ----------

    function crearBotonesDeContenedor(parada) {
      const id = parada.id;
      const botonVaciar = crearBoton('a.vaciar', async function () {
        const resultado = await AccionesContenedor.registrarVaciado(id);
        if (resultado !== null && resultado !== undefined && resultado.exito) {
          await recargarContenedores();
          // Con el contenedor vaciado, la próxima parada pasa a la siguiente.
          irA(indice + 1);
        }
      }, 'btn pri');

      return crearElemento('div', { clase: 'ruta-acc' },
        botonVaciar,
        crearBoton('a.llenado', function () { AccionesContenedor.informarLlenado(id, recargarContenedores); }, 'btn'),
        crearBoton('a.estado', function () { AccionesContenedor.reportarEstado(id, recargarContenedores); }, 'btn'),
        crearBoton('a.incidente', function () { AccionesContenedor.reportarIncidente(id, recargarContenedores); }, 'btn'),
        crearBoton('a.detalle', function () { AccionesContenedor.abrirDetalle(id); }, 'btn')
      );
    }

    function pintarPanel() {
      const parada = paradas[indice];
      const esContenedor = parada.tipo === 'contenedor';
      textoDistancia = crearElemento('span', { clase: 'hint' });

      const contenido = [];

      contenido.push(crearElemento('div', { clase: 'ruta-info' },
        crearElemento('span', { clase: 'tag', style: 'background:' + colores.proxima + ';color:#fff' }, traducir('ruta.proxima')),
        crearElemento('span', { clase: 'hint', texto: traducir('ruta.parada') + ' ' + (indice + 1) + ' / ' + paradas.length }),
        textoDistancia
      ));

      contenido.push(crearElemento('strong', { texto: tituloDeParada(parada.tipo, parada.id) }));
      contenido.push(crearElemento('div', { texto: parada.datos.UBICACION || '' }));

      if (esContenedor) {
        contenido.push(crearEtiquetas(parada.datos.TIPO_BASURAS));
        const nivel = parada.datos.LLENADO || 0;
        contenido.push(crearElemento('div', { texto: traducir('k.LLENADO') + ': ' + nivel + '/' + LLENADO_MAXIMO }));
        if (convertirALista(parada.datos.CONDICION).length > 0) {
          contenido.push(crearEtiquetas(parada.datos.CONDICION, 'warn'));
        }
      } else {
        contenido.push(crearElemento('div', { clase: 'hint', texto: traducir('lg.centro') }));
      }

      contenido.push(crearElemento('div', { clase: 'ruta-nav' },
        crearElemento('button', { type: 'button', clase: 'btn', disabled: indice === 0, alHacerClick: function () { irA(indice - 1); } }, '◀ ' + traducir('ruta.anterior')),
        crearElemento('button', { type: 'button', clase: 'btn', disabled: indice === paradas.length - 1, alHacerClick: function () { irA(indice + 1); } }, traducir('ruta.siguiente') + ' ▶')
      ));

      if (esContenedor) {
        contenido.push(crearBotonesDeContenedor(parada));
      }

      contenido.push(crearElemento('div', { clase: 'ruta-acc' },
        crearElemento('button', { type: 'button', clase: 'btn sm', alHacerClick: ajustarATodaLaRuta }, traducir('ruta.vertodo')),
        crearElemento('button', { type: 'button', clase: 'btn sm', alHacerClick: centrarEnMiPosicion }, traducir('ruta.centrar'))
      ));

      zonaDeParada.replaceChildren(...contenido);
      actualizarDistancia();
    }

    // ---------- Arranque ----------

    try {
      await Mapas.cargar();
    } catch (error) {
      if (!cerrado) {
        mostrarError(traducir('map.err'));
      }
      return;
    }
    if (cerrado) {
      return;
    }

    try {
      await cargarDatos();
    } catch (error) {
      if (!cerrado) {
        mostrarError(error.message || traducir('err.generic'));
      }
      return;
    }
    if (cerrado) {
      return;
    }

    if (paradas.length === 0) {
      mostrarError(traducir('ruta.sinparadas'));
      return;
    }

    // Empieza en la parada donde quedó; si es la primera vez, en el primer contenedor
    // (el centro de inicio es de donde sale el camión).
    const guardado = leerIndiceGuardado();
    if (guardado !== null) {
      indice = guardado;
    } else if (paradas.length > 1 && paradas[0].tipo === 'centro') {
      indice = 1;
    }

    mapa = Mapas.crear(cajaDelMapa, CONFIGURACION.CENTRO_DEL_MAPA, CONFIGURACION.ZOOM_DEL_MAPA);
    const puntosDeLaRuta = [];
    for (const parada of paradas) {
      puntosDeLaRuta.push([parada.pos.lat, parada.pos.lng]);
    }
    lineaDeRuta = L.polyline(puntosDeLaRuta, { color: colores.linea, weight: 3, opacity: 0.6, dashArray: '6 8' }).addTo(mapa);

    dibujarMapa();
    pintarPanel();
    mapa.invalidateSize();
    ajustarATodaLaRuta();
    iniciarGps();
  }
};
