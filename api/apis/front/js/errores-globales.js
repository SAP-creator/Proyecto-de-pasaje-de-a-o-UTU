/*
  errores-globales.js
  Si algo falla en JavaScript, muestra un aviso rojo arriba de la página.
  Así nunca queda una pantalla en blanco sin explicación.
*/

function mostrarErrorFatal(mensaje) {
  let caja = document.getElementById('error-fatal');

  if (!caja) {
    caja = document.createElement('div');
    caja.id = 'error-fatal';
    caja.setAttribute('role', 'alert');
    caja.style.position = 'fixed';
    caja.style.top = '0';
    caja.style.left = '0';
    caja.style.right = '0';
    caja.style.zIndex = '99';
    caja.style.background = '#8f1d27';
    caja.style.color = '#fff';
    caja.style.padding = '0.6rem 1rem';
    caja.style.fontFamily = 'sans-serif';
    caja.style.fontSize = '14px';
    caja.style.whiteSpace = 'pre-wrap';

    const contenedor = document.body || document.documentElement;
    contenedor.prepend(caja);
  }

  caja.textContent = caja.textContent + mensaje + '\n';
}

window.addEventListener('error', function (evento) {
  const archivo = String(evento.filename || '').split('/').pop();
  mostrarErrorFatal('Error: ' + evento.message + ' (' + archivo + ':' + evento.lineno + ')');
});

window.addEventListener('unhandledrejection', function (evento) {
  const motivo = evento.reason;
  let texto = motivo;
  if (motivo && motivo.message) {
    texto = motivo.message;
  }
  mostrarErrorFatal('Error: ' + texto);
});
