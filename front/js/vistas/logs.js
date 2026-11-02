/*
  vistas/logs.js
  Solo admin sistema: auditoría. Dos pestañas internas:
    "users" -> acciones de los usuarios (GET /logs/users)
    "sql"   -> consultas a la base de datos (GET /logs/sql)
*/

function crearVistaLogs(seccion) {
  const filtroTexto = crearElemento('input', { clavePlaceholder: 'f.texto' });
  const zonaTabla = crearElemento('div', {});
  const barraDePestanas = crearElemento('div', { clase: 'subtabs' });

  let tipoDeLogActual = 'users';
  let todosLosLogs = [];

  const botonUsuarios = crearElemento('button', { type: 'button', clase: 'tab active', alHacerClick: function () { elegirTipo('users'); } }, traducir('logs.users'));
  const botonSql = crearElemento('button', { type: 'button', clase: 'tab', alHacerClick: function () { elegirTipo('sql'); } }, traducir('logs.sql'));
  barraDePestanas.append(botonUsuarios, botonSql);

  seccion.append(crearTituloDeSeccion('tab.logs'));
  seccion.append(barraDePestanas);
  seccion.append(crearElemento('div', { clase: 'filters' }, crearFiltro('f.texto', filtroTexto)));
  seccion.append(zonaTabla);

  filtroTexto.addEventListener('input', dibujarTabla);

  function elegirTipo(tipo) {
    tipoDeLogActual = tipo;
    botonUsuarios.classList.toggle('active', tipo === 'users');
    botonSql.classList.toggle('active', tipo === 'sql');
    cargarLogs();
  }

  const columnasDeUsuarios = [
    { titulo: 'k.FECHA', valor: function (l) { return l.FECHA; } },
    { titulo: 'k.CI', valor: function (l) { return l.CEDULA_USUARIO; } },
    { titulo: 'k.TYPELOG', valor: function (l) { return l.TYPELOG; } },
    { titulo: 'k.TEXTO', valor: function (l) { return l.TEXTO; } }
  ];

  // En los logs SQL, la consulta y sus parámetros se muestran dentro de un desplegable.
  function crearCeldaDeTextoSql(log) {
    const celda = crearElemento('div', {}, log.TEXTO);
    if (log.query || log.parametros) {
      const lineas = [];
      if (log.query) {
        lineas.push(log.query);
      }
      if (log.parametros) {
        lineas.push(log.parametros);
      }
      const detalle = crearElemento('pre', { texto: lineas.join('\n'), style: 'white-space:pre-wrap;margin:.4rem 0' });
      celda.append(crearElemento('details', {}, crearElemento('summary', { claveTexto: 'detalle' }), detalle));
    }
    return celda;
  }

  const columnasSql = [
    { titulo: 'k.FECHA', valor: function (l) { return l.FECHA; } },
    { titulo: 'k.TIPO_MODELO', valor: function (l) { return l.TIPO_MODELO; } },
    { titulo: 'k.TEXTO', valor: crearCeldaDeTextoSql }
  ];

  function dibujarTabla() {
    const textoBuscado = filtroTexto.value.toLowerCase();
    const visibles = [];
    for (const log of todosLosLogs) {
      if (JSON.stringify(log).toLowerCase().includes(textoBuscado)) {
        visibles.push(log);
      }
    }

    let columnas = columnasDeUsuarios;
    if (tipoDeLogActual === 'sql') {
      columnas = columnasSql;
    }
    zonaTabla.replaceChildren(construirTabla(columnas, visibles));
  }

  async function cargarLogs() {
    mostrarCargando(zonaTabla);

    let ruta = '/logs/users';
    if (tipoDeLogActual === 'sql') {
      ruta = '/logs/sql';
    }

    const respuesta = await Api.llamarComoRol('GET', ruta);
    if (!respuesta.exito) {
      mostrarMensajeDeError(zonaTabla, respuesta.mensaje);
      return;
    }
    todosLosLogs = respuesta.lista;
    dibujarTabla();
  }

  return { mostrar: cargarLogs };
}

VISTAS.logs = crearVistaLogs;
