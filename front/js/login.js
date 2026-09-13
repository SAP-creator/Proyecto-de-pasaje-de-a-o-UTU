/**
 * login.js
 * Única función: controlar la página login.html (alternar entre las
 * pestañas "Iniciar sesión" / "Registrarse" y manejar el envío de ambos
 * formularios). Habla con la API a través de "auth", pero no sabe nada de
 * cómo se guarda o se lee la sesión: eso es responsabilidad exclusiva de
 * auth.js.
 */

function switchTab(tab) {
  tab_login.classList.toggle('active', tab === 'login');
  tab_register.classList.toggle('active', tab === 'register');
  form_login.classList.toggle('hidden', tab !== 'login');
  form_register.classList.toggle('hidden', tab !== 'register');

}

function mostrarMensaje(el, texto, tipo) {
  el.textContent = texto;
  el.classList.remove('hidden', 'error', 'exito');
  el.classList.add(tipo);
}

tab_login.addEventListener('click', () => switchTab('login'));
tab_register.addEventListener('click', () => switchTab('register'));
link_ir_registro.addEventListener('click', () => switchTab('register'));
link_ir_login.addEventListener('click', () => switchTab('login'));

formulario_login.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const cedula = login_cedula.value.trim();
  const password = login_password.value;

  const boton = evento.target.querySelector('button');
  boton.disabled = true;
  
  try {
    await auth.iniciarSesion(cedula, password);
    location.href = '../app.html';
  } catch (error) {
    mostrarMensaje(mensaje_login, error.message, 'error');
  } finally {
    boton.disabled = false;
  }
});

formulario_registro.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const cedula = registro_cedula.value.trim();
  const tipo = registro_tipo.value;
  const password = registro_password.value;
  const confirmar = registro_confirmar.value;

  if (!tipo) {
    mostrarMensaje(mensaje_register, 'Seleccioná un tipo de usuario.', 'error');
    return;
  }
  if (password !== confirmar) {
    mostrarMensaje(mensaje_register, 'Las contraseñas no coinciden.', 'error');
    return;
  }

  const boton = evento.target.querySelector('button');
  boton.disabled = true;

  try {
    await auth.registrarSolicitud(cedula, password, tipo);
    mostrarMensaje(mensaje_register, 'Listo, tu solicitud fue enviada. Un administrador del sistema tiene que aprobarla antes de que puedas iniciar sesión.', 'exito');
    evento.target.reset();
  } catch (error) {
    mostrarMensaje(mensaje_register, error.message, 'error');
  } finally {
    boton.disabled = false;
  }
});
