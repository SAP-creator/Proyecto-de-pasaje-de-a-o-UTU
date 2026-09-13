/**
 * ui.js
 * Única función: mecanismos genéricos de interfaz (abrir/cerrar la ventana
 * modal #1-#2-etc, el mini-popup de confirmación #F, y armar la barra de
 * tabs). No sabe nada de la API ni de usuarios: solo mueve elementos del DOM
 * que ya existen como <template> en el HTML.
 */

const Modal = {
  /**
   * Abre la ventana modal genérica (la de app.html) mostrando el contenido
   * de un <template> ya clonado. "contenidoNodo" tiene que ser un Node,
   * nunca un string HTML.
   *
   * Por defecto se puede cerrar con el botón "✕". Pasar { cerrable: false }
   * para un popup bloqueante (ej: completar perfil obligatorio).
   */
  abrir(titulo, contenidoNodo, { cerrable = true } = {}) {
    modal_titulo.textContent = titulo;
    modal_contenido.replaceChildren(contenidoNodo);
    modal_cerrar_boton.classList.toggle('hidden', !cerrable);
    modal_overlay.classList.remove('hidden');
  },

  cerrar() {
    modal_overlay.classList.add('hidden');
    modal_contenido.replaceChildren();
  },

  /**
   * Mini-popup de confirmación (#F). Devuelve una promesa que resuelve en
   * true (SI) o false (NO).
   */
  confirmar(mensaje) {
    return new Promise((resolver) => {
      confirm_mensaje.textContent = mensaje;
      confirm_overlay.classList.remove('hidden');

      const limpiar = () => {
        confirm_overlay.classList.add('hidden');
        confirm_si.removeEventListener('click', onSi);
        confirm_no.removeEventListener('click', onNo);
      };
      const onSi = () => { limpiar(); resolver(true); };
      const onNo = () => { limpiar(); resolver(false); };

      confirm_si.addEventListener('click', onSi);
      confirm_no.addEventListener('click', onNo);
    });
  }
};

// El botón "✕" del modal genérico (#modal_cerrar_boton) cierra el modal.
modal_cerrar_boton.addEventListener('click', () => Modal.cerrar());

const Tabs = {
  /**
   * Muestra una sección (id sin guiones, ej: "seccion_mapa") y esconde
   * las demás dentro de un contenedor de secciones dado.
   */
  mostrarSeccion(idSeccion, selectorHermanos) {
    document.querySelectorAll(selectorHermanos).forEach(s => s.classList.remove('active'));
    window[idSeccion].classList.add('active');
  },

  /**
   * Arma la barra de tabs principal (#app_tabs) según la lista de botones
   * que le pase app.js para el rol logueado.
   * botones: [{ texto: 'Mapa', idSeccion: 'seccion_mapa' }, ...]
   */
  armarBarraPrincipal(botones) {
    app_tabs.replaceChildren();
    
    // 1. Buscamos si había un tab guardado
    const tabGuardado = sessionStorage.getItem('tab_activo');
    let indexInicial = 0;

    botones.forEach((btn, indice) => {
      // 2. Si coincide con el guardado, actualizamos el índice inicial
      if (tabGuardado === btn.idSeccion) {
        indexInicial = indice;
      }

      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'app-tab' + (indice === indexInicial ? ' active' : '');
      boton.textContent = btn.texto;
      
      boton.addEventListener('click', () => {
        app_tabs.querySelectorAll('.app-tab').forEach(b => b.classList.remove('active'));
        boton.classList.add('active');
        Tabs.mostrarSeccion(btn.idSeccion, '.seccion-app');
        
        // 3. Guardamos la selección cada vez que hace click
        sessionStorage.setItem('tab_activo', btn.idSeccion);
      });
      app_tabs.appendChild(boton);
    });

    // 4. Mostramos la sección correspondiente
    if (botones[indexInicial]) {
      Tabs.mostrarSeccion(botones[indexInicial].idSeccion, '.seccion-app');
    }
  },

  /**
   * Arma una barra de sub-tabs genérica (para el "Menu logs": |Logs usuario| |Logs BD|)
   * dentro de cualquier contenedor .tabs-nav / conjunto de .tab-panel.
   */
  armarSubTabs(contenedorNav, botones) {
    contenedorNav.replaceChildren();
    botones.forEach((btn, indice) => {
      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'tab-btn' + (indice === 0 ? ' active' : '');
      boton.textContent = btn.texto;
      boton.addEventListener('click', () => {
        contenedorNav.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        boton.classList.add('active');
        document.querySelectorAll('.tab-panel').forEach(p => {
          if (p.dataset.grupo === contenedorNav.dataset.grupo) p.classList.remove('active');
        });
        window[btn.idPanel].classList.add('active');
      });
      contenedorNav.appendChild(boton);
    });
    if (botones[0]) window[botones[0].idPanel].classList.add('active');
  }
};
