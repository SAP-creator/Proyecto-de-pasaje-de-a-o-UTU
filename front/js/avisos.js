/*
  avisos.js
  Avisos temporales (abajo a la derecha), ventanas modales y confirmaciones.
*/

function mostrarAviso(mensaje, tipo) {
  let caja = document.getElementById('avisos');
  if (!caja) {
    caja = crearElemento('div', { id: 'avisos', 'aria-live': 'polite' });
    document.body.append(caja);
  }

  const aviso = crearElemento('div', { clase: 'toast ' + (tipo || ''), texto: mensaje });
  caja.append(aviso);

  // El aviso desaparece solo a los 4,5 segundos.
  setTimeout(function () {
    aviso.remove();
  }, 4500);
}

const Modal = {

  // Abre una ventana con un título y un contenido. Devuelve { cerrar, elemento }.
  abrir: function (titulo, contenido, opciones) {
    const configuracion = opciones || {};
    const sePuedeCerrar = configuracion.sePuedeCerrar !== false;

    const fondo = crearElemento('div', { clase: 'ov', role: 'dialog', 'aria-modal': 'true' });

    function cerrar() {
      fondo.remove();
    }

    const cabecera = crearElemento('div', { clase: 'modal-h' }, crearElemento('h3', { texto: titulo }));
    if (sePuedeCerrar) {
      const botonCerrar = crearElemento('button', {
        type: 'button',
        clase: 'btn ghost sm',
        'aria-label': traducir('close'),
        alHacerClick: cerrar
      }, '✕');
      cabecera.append(botonCerrar);
    }

    const cuerpo = crearElemento('div', { clase: 'modal-b' }, contenido);

    let claseDeLaVentana = 'modal';
    if (configuracion.ancho) {
      claseDeLaVentana = 'modal wide';
    }
    const ventana = crearElemento('div', { clase: claseDeLaVentana }, cabecera, cuerpo);

    fondo.append(ventana);
    document.body.append(fondo);

    return { cerrar: cerrar, elemento: fondo, cuerpo: cuerpo };
  },

  // Pregunta "¿Seguro?" y devuelve una promesa con true (sí) o false (no).
  confirmar: function (mensaje) {
    return new Promise(function (resolver) {
      let ventana = null;

      function responder(respuesta) {
        ventana.cerrar();
        resolver(respuesta);
      }

      const botones = crearElemento('div', { clase: 'row-btns' },
        crearElemento('button', { type: 'button', clase: 'btn', alHacerClick: function () { responder(false); } }, traducir('no')),
        crearElemento('button', { type: 'button', clase: 'btn pri', alHacerClick: function () { responder(true); } }, traducir('yes'))
      );

      ventana = Modal.abrir(traducir('confirm'), [crearElemento('p', { texto: mensaje }), botones], { sePuedeCerrar: false });
    });
  }
};

// Espera el resultado de una llamada a la API y muestra un aviso verde (éxito) o rojo (error).
async function ejecutarYAvisar(promesaDeLaApi) {
  const respuesta = await promesaDeLaApi;
  if (respuesta.exito) {
    mostrarAviso(respuesta.mensaje, 'ok');
  } else {
    mostrarAviso(respuesta.mensaje, 'error');
  }
  return respuesta;
}

// Pide confirmación; si la persona acepta, ejecuta la acción. Devuelve lo que devuelva la acción.
async function confirmarYEjecutar(mensaje, accion) {
  const aceptado = await Modal.confirmar(mensaje);
  if (aceptado) {
    return await accion();
  }
  return null;
}
