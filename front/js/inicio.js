/*
  inicio.js
  Página principal (index.html): arma la barra superior y traduce los textos.
  El resto de la página está escrito directamente en index.html.
*/

function iniciarPaginaPrincipal() {
  const enlacesDeNavegacion = crearElemento('nav', { clase: 'nav' },
    crearElemento('a', { href: RAIZ + 'index.html', clase: 'on', claveTexto: 'nav.home' }),
    crearElemento('a', { href: RAIZ + 'app.html', claveTexto: 'nav.map' }),
    crearElemento('a', { href: '#contacto', claveTexto: 'nav.contact' })
  );

  const zonaDerecha = crearElemento('div', { clase: 'right' },
    Encabezado.crearSelectorDeIdioma(),
    crearElemento('a', { clase: 'btn ghost sm', href: RAIZ + 'pages/login.html', claveTexto: 'nav.login' }),
    crearElemento('a', { clase: 'btn pri pill sm', href: RAIZ + 'pages/login.html?m=up', claveTexto: 'nav.signup' })
  );

  document.getElementById('top').append(Encabezado.crearLogos(), enlacesDeNavegacion, zonaDerecha);
  Encabezado.montarPie();
  traducirPagina();
}

iniciarPaginaPrincipal();
