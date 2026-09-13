/**
 * index.js
 * Única función: manejar las interacciones de la página de inicio
 * (index.html). Por ahora, el botón "Sign up" de la sección CTA, que
 * lleva a la pantalla de login/registro.
 */

cta_ir_registro.addEventListener('click', () => {
  location.href = 'pages/login.html';
});
