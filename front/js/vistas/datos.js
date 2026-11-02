/*
  vistas/datos.js
  "Mis datos": la persona ve su cédula y tipo de usuario, y puede cambiar su
  contraseña (y su nombre y apellido si no es vecino).
  El backend no tiene una ruta para leer nombre y apellido, por eso esos campos empiezan vacíos.
*/

function crearVistaDatos(seccion) {
  const esVecino = rolActualEs('V');

  // Crea una etiqueta con un campo de texto adentro.
  function crearCampoDeTexto(nombre, claveEtiqueta, opciones) {
    const configuracion = opciones || {};
    const entrada = crearElemento('input', {
      name: nombre,
      type: configuracion.tipo || 'text',
      value: configuracion.valor,
      disabled: configuracion.deshabilitado,
      clavePlaceholder: configuracion.clavePlaceholder,
      autocomplete: 'off'
    });
    let clase = 'f';
    if (configuracion.anchoCompleto) {
      clase = 'f span2';
    }
    return crearElemento('label', { clase: clase }, traducir(claveEtiqueta), entrada);
  }

  const cajaDeMensaje = crearElemento('div', { clase: 'msg hidden', role: 'status' });
  const formulario = crearElemento('form', { clase: 'grid2', novalidate: true, alEnviar: guardarCambios });

  formulario.append(crearCampoDeTexto('ci', 'k.CI', { valor: Sesion.cedula(), deshabilitado: true }));
  formulario.append(crearCampoDeTexto('tipo', 'k.TYPEUSER', { valor: traducirValor(Sesion.tipo()), deshabilitado: true }));
  if (!esVecino) {
    formulario.append(crearCampoDeTexto('FIRSTNAME', 'k.FIRSTNAME'));
    formulario.append(crearCampoDeTexto('LASTNAME', 'k.LASTNAME'));
  }
  formulario.append(crearCampoDeTexto('PASSWORD', 'k.newpass', { tipo: 'password', anchoCompleto: true, clavePlaceholder: 'ph.blank' }));
  formulario.append(crearElemento('div', { clase: 'span2' }, cajaDeMensaje));
  formulario.append(crearElemento('div', { clase: 'span2' },
    crearElemento('button', { type: 'submit', clase: 'btn pri pill' }, traducir('save'))));

  seccion.append(crearTituloDeSeccion('tab.datos'));
  seccion.append(crearElemento('div', { clase: 'panel' }, formulario));

  function mostrarMensaje(texto, tipo) {
    cajaDeMensaje.textContent = texto;
    cajaDeMensaje.className = 'msg ' + tipo;
  }

  async function guardarCambios(evento) {
    evento.preventDefault();

    // Solo se envían los campos que la persona completó.
    const datosDelUsuario = {};
    const nombresDeCampos = ['FIRSTNAME', 'LASTNAME', 'PASSWORD'];
    for (const nombre of nombresDeCampos) {
      const campo = formulario.elements[nombre];
      if (campo && campo.value.trim() !== '') {
        datosDelUsuario[nombre] = campo.value.trim();
      }
    }

    if (Object.keys(datosDelUsuario).length === 0) {
      mostrarMensaje(traducir('nothing'), 'error');
      return;
    }

    const respuesta = await Api.llamar('PUT', '/user/profile', { USER: datosDelUsuario });
    if (respuesta.exito) {
      mostrarMensaje(respuesta.mensaje, 'ok');
      formulario.elements.PASSWORD.value = '';
    } else {
      mostrarMensaje(respuesta.mensaje, 'error');
    }
  }

  // No hay nada que cargar al entrar a esta pestaña.
  return { mostrar: function () { } };
}

VISTAS.datos = crearVistaDatos;
