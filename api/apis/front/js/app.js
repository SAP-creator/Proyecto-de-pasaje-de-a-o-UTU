/*
  app.js
  Arranque de app.html: comprueba la sesión, pide los datos que falten del perfil
  y arma las pestañas según el rol.
*/

const App = {
  vistas: {},
  botonesDePestana: {},
  secciones: {},

  // Cambia a una pestaña. "parametros" es opcional (por ejemplo { ruta: 3 } para el mapa).
  irAPestana: function (nombre, parametros) {
    if (!this.vistas[nombre]) {
      return;
    }

    for (const otroNombre of Object.keys(this.secciones)) {
      const esLaElegida = otroNombre === nombre;
      this.secciones[otroNombre].classList.toggle('active', esLaElegida);
      this.botonesDePestana[otroNombre].classList.toggle('active', esLaElegida);
      this.botonesDePestana[otroNombre].setAttribute('aria-selected', String(esLaElegida));
    }

    sessionStorage.setItem('sger_pestana', nombre);
    this.vistas[nombre].mostrar(parametros);
  },

  // Crea una pestaña y una sección por cada vista que le corresponde al rol.
  armarPestanas: function () {
    const barraDePestanas = document.getElementById('tabs');
    const zonaPrincipal = document.getElementById('main');
    const nombresDePestanas = PESTANAS_POR_ROL[Sesion.tipo()] || ['mapa', 'datos'];
    const app = this;

    for (const nombre of nombresDePestanas) {
      const seccion = crearElemento('section', { clase: 'sec', id: 'sec-' + nombre });
      const boton = crearElemento('button', {
        type: 'button',
        clase: 'tab',
        role: 'tab',
        alHacerClick: function () { app.irAPestana(nombre); }
      }, traducir('tab.' + nombre));

      zonaPrincipal.append(seccion);
      barraDePestanas.append(boton);

      this.secciones[nombre] = seccion;
      this.botonesDePestana[nombre] = boton;
      this.vistas[nombre] = VISTAS[nombre](seccion);
    }

    // Se vuelve a la última pestaña usada, si sigue disponible para este rol.
    const ultimaPestana = sessionStorage.getItem('sger_pestana');
    if (nombresDePestanas.includes(ultimaPestana)) {
      this.irAPestana(ultimaPestana);
    } else {
      this.irAPestana(nombresDePestanas[0]);
    }
  },

  // Si el perfil está incompleto (falta nombre y apellido), se piden antes de usar la app.
  pedirDatosFaltantes: function () {
    const app = this;

    abrirFormulario({
      titulo: traducir('login.complete'),
      sinCerrar: true,
      campos: [
        { nombre: 'FIRSTNAME', etiqueta: 'k.FIRSTNAME', obligatorio: true, ayuda: 'login.complete.hint' },
        { nombre: 'LASTNAME', etiqueta: 'k.LASTNAME', obligatorio: true }
      ],
      enviar: async function (valores) {
        const respuesta = await Api.llamar('PUT', '/user/complete', { USER: valores });
        if (!respuesta.exito) {
          return respuesta;
        }

        // El backend devuelve un token nuevo (con el perfil ya completo y su firma nueva).
        if (respuesta.datos.TOKEN && respuesta.datos.TOKEN.USER) {
          Sesion.guardarToken(respuesta.datos.TOKEN);
          return respuesta;
        }

        // Si no lo devolvió, no se puede inventar uno (la firma solo la genera el backend):
        // se cierra la sesión para que la persona vuelva a ingresar con el perfil ya completo.
        mostrarAviso(traducir('login.relogin'), 'ok');
        setTimeout(function () {
          Sesion.cerrarSesion();
        }, 1800);
        return respuesta;
      },
      alTerminar: function () {
        if (Sesion.hayUsuarioIngresado() && Sesion.perfilCompleto()) {
          app.armarPestanas();
        }
      }
    });
  }
};

function arrancarApp() {
  if (!Sesion.hayUsuarioIngresado()) {
    location.href = RAIZ + 'pages/login.html';
    return;
  }

  // Barra superior: logos, pestañas (se agregan después), idioma, tipo de usuario y salir.
  const botonSalir = crearElemento('button', { type: 'button', clase: 'btn danger sm', alHacerClick: function () { Sesion.cerrarSesion(); } }, traducir('nav.logout'));
  const zonaDerecha = crearElemento('div', { clase: 'right' },
    Encabezado.crearSelectorDeIdioma(),
    crearElemento('span', { clase: 'chip', texto: traducirValor(Sesion.tipo()) }),
    botonSalir
  );

  const barraSuperior = document.getElementById('top');
  barraSuperior.append(
    Encabezado.crearLogos(),
    crearElemento('nav', { clase: 'nav', id: 'tabs', role: 'tablist' }),
    zonaDerecha
  );
  Encabezado.montarPie();

  if (Sesion.perfilCompleto()) {
    App.armarPestanas();
  } else {
    App.pedirDatosFaltantes();
  }
}

arrancarApp();
