/**
 * menu-completar.js
 * Única función: mostrar el popup bloqueante que le pide al usuario
 * logueado completar Nombre y Apellido cuando su perfil todavía no está
 * completo (TOKEN.USER.COMPLETEUSER === false), y avisarle a app.js
 * cuando ya puede continuar.
 */

const LISTA_MAPEO_COMPLETAR = {
  'Error C ApiAdminSys InvalidMissingDataProvided': 'Completá los datos solicitados.',
  'Error C ApiAdminSys IncompleteDataDbError': 'No pudimos verificar tu perfil. Intentá de nuevo.',
  'Error C ApiAdminSys UserCheckException': 'No pudimos verificar tu perfil. Intentá de nuevo.',
  'OK C ApiAdminSys UserAlreadyComplete': 'Tu perfil ya estaba completo.',
  'OK C ApiAdminSys AllDataAlreadyComplete': 'Tu perfil ya estaba completo.'
};

const MenuCompletar = {
  /**
   * @param {Function} alCompletar Callback que arranca el resto de la app
   *                               una vez que el perfil queda completo.
   */
  mostrar(alCompletar) {
    const contenedor = document.createElement('div');
    contenedor.appendChild(tpl_modal_completar_perfil.content.cloneNode(true));

    const form = contenedor.querySelector('form');
    const mensaje = contenedor.querySelector('[data-campo="mensaje"]');

    Modal.abrir('Completá tu perfil', contenedor, { cerrable: false });

    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      const boton = form.querySelector('button');
      boton.disabled = true;


      const resultado = await ApiCliente.fetchDatos('USER_COMPLETE', {
        TOKEN:auth.tokenUsuario() ,
        USER: {
          FIRSTNAME: form.FIRSTNAME.value.trim(),
          LASTNAME: form.LASTNAME.value.trim()
        }
      }, LISTA_MAPEO_COMPLETAR);

      boton.disabled = false;

      if (resultado.esError) {
        this._mostrarMensaje(mensaje, resultado.mensajeUsuario, 'error');
        return;
      }

      auth.marcarPerfilCompleto();
      Modal.cerrar();
      alCompletar();
    });
  },

  _mostrarMensaje(elemento, texto, tipo) {
    elemento.textContent = texto;
    elemento.classList.remove('hidden', 'error', 'exito');
    elemento.classList.add(tipo);
  }
};
