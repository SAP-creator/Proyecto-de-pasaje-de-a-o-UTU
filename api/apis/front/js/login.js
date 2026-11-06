/*
  login.js
  Página de ingreso y registro. Los dos formularios están escritos en pages/login.html;
  este archivo solo muestra uno u otro y envía los datos al backend.
    Ingresar:     POST /user/sign/in  -> devuelve el token de sesión
    Registrarse:  POST /user/sign/up  -> crea una solicitud que un admin sistema debe aceptar
*/

// ---------- Elementos de la página ----------
const botonPestanaIngresar = document.getElementById('boton-ingresar');
const botonPestanaRegistrarse = document.getElementById('boton-registrarse');
const formularioIngreso = document.getElementById('form-ingreso');
const formularioRegistro = document.getElementById('form-registro');
const mensajeIngreso = document.getElementById('mensaje-ingreso');
const mensajeRegistro = document.getElementById('mensaje-registro');
const selectorTipoUsuario = document.getElementById('registro-tipo');

// ---------- Mostrar un formulario u otro ----------

function mostrarFormularioDeIngreso() {
  formularioIngreso.classList.remove('hidden');
  formularioRegistro.classList.add('hidden');
  botonPestanaIngresar.classList.add('on');
  botonPestanaRegistrarse.classList.remove('on');
  ocultarMensaje(mensajeIngreso);
}

function mostrarFormularioDeRegistro() {
  formularioRegistro.classList.remove('hidden');
  formularioIngreso.classList.add('hidden');
  botonPestanaRegistrarse.classList.add('on');
  botonPestanaIngresar.classList.remove('on');
  ocultarMensaje(mensajeRegistro);
}

// ---------- Mensajes ----------

function mostrarMensaje(caja, texto, tipo) {
  caja.textContent = texto;
  caja.className = 'msg ' + tipo;
}

function ocultarMensaje(caja) {
  caja.className = 'msg hidden';
}

// ---------- Validaciones ----------

// La cédula debe tener solo dígitos y hasta 9 (límite del backend).
function cedulaEsValida(texto) {
  if (texto === '') {
    return false;
  }
  if (texto.length > 9) {
    return false;
  }
  for (const caracter of texto) {
    if (caracter < '0' || caracter > '9') {
      return false;
    }
  }
  return true;
}

// ---------- Ingresar ----------

async function enviarIngreso(evento) {
  evento.preventDefault();
  ocultarMensaje(mensajeIngreso);

  const cedula = formularioIngreso.elements.ci.value.trim();
  const contrasena = formularioIngreso.elements.password.value;

  if (!cedulaEsValida(cedula)) {
    mostrarMensaje(mensajeIngreso, traducir('login.badci'), 'error');
    return;
  }
  if (contrasena === '') {
    mostrarMensaje(mensajeIngreso, traducir('req') + ': ' + traducir('k.PASSWORD'), 'error');
    return;
  }

  const botonEnviar = formularioIngreso.querySelector('button[type=submit]');
  botonEnviar.disabled = true;

  const respuesta = await Api.llamar('POST', '/user/sign/in', {
    USER: { CI: Number(cedula), PASSWORD: contrasena }
  });

  botonEnviar.disabled = false;

  if (!respuesta.exito) {
    mostrarMensaje(mensajeIngreso, respuesta.mensaje, 'error');
    return;
  }

  const token = respuesta.datos.TOKEN;
  if (!token || !token.USER) {
    mostrarMensaje(mensajeIngreso, traducir('err.generic'), 'error');
    return;
  }

  Sesion.guardarToken(token);
  location.href = RAIZ + 'app.html';
}

// ---------- Registrarse ----------

async function enviarRegistro(evento) {
  evento.preventDefault();
  ocultarMensaje(mensajeRegistro);

  const cedula = formularioRegistro.elements.ci.value.trim();
  const tipoDeUsuario = formularioRegistro.elements.tipo.value;
  const contrasena = formularioRegistro.elements.password.value;
  const contrasenaRepetida = formularioRegistro.elements.password2.value;

  if (!cedulaEsValida(cedula)) {
    mostrarMensaje(mensajeRegistro, traducir('login.badci'), 'error');
    return;
  }
  if (contrasena === '') {
    mostrarMensaje(mensajeRegistro, traducir('req') + ': ' + traducir('k.PASSWORD'), 'error');
    return;
  }
  if (contrasena !== contrasenaRepetida) {
    mostrarMensaje(mensajeRegistro, traducir('login.mismatch'), 'error');
    return;
  }

  const botonEnviar = formularioRegistro.querySelector('button[type=submit]');
  botonEnviar.disabled = true;

  const respuesta = await Api.llamar('POST', '/user/sign/up', {
    USER: { CI: Number(cedula), PASSWORD: contrasena, TYPEUSER: tipoDeUsuario }
  });

  botonEnviar.disabled = false;

  if (!respuesta.exito) {
    mostrarMensaje(mensajeRegistro, respuesta.mensaje, 'error');
    return;
  }

  // La solicitud quedó pendiente: se vuelve al ingreso avisando que hay que esperar la aprobación.
  formularioRegistro.reset();
  mostrarFormularioDeIngreso();
  mostrarMensaje(mensajeIngreso, traducir('login.sent'), 'ok');
}

// ---------- Inicio de la página ----------

function iniciarPaginaDeLogin() {
  // Si ya hay una sesión abierta, no tiene sentido ver el login.
  if (Sesion.hayUsuarioIngresado()) {
    location.href = RAIZ + 'app.html';
    return;
  }

  // Barra superior: logos a la izquierda; idioma y "Volver al inicio" a la derecha.
  const enlaceVolver = crearElemento('a', { clase: 'btn ghost sm', href: RAIZ + 'index.html', claveTexto: 'login.back' });
  const zonaDerecha = crearElemento('div', { clase: 'right' }, Encabezado.crearSelectorDeIdioma(), enlaceVolver);
  document.getElementById('top').append(Encabezado.crearLogos(), zonaDerecha);
  Encabezado.montarPie();

  // Rellenar la lista de tipos de usuario del registro.
  for (const tipo of TIPOS_USUARIO) {
    selectorTipoUsuario.append(crearOpcion(tipo, traducirValor(tipo), false));
  }

  traducirPagina();

  botonPestanaIngresar.addEventListener('click', mostrarFormularioDeIngreso);
  botonPestanaRegistrarse.addEventListener('click', mostrarFormularioDeRegistro);
  formularioIngreso.addEventListener('submit', enviarIngreso);
  formularioRegistro.addEventListener('submit', enviarRegistro);

  // La landing enlaza a "login.html?m=up" para abrir directo el registro.
  const parametros = new URLSearchParams(location.search);
  if (parametros.get('m') === 'up') {
    mostrarFormularioDeRegistro();
  }
}

iniciarPaginaDeLogin();
