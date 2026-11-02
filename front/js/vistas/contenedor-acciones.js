/*
  contenedor-acciones.js
  Acciones sobre un contenedor que se usan desde varias pantallas
  (la tabla de contenedores y el mapa).
*/

const AccionesContenedor = {

  // Ventana con todos los datos de un contenedor y los botones de acciones según el rol.
  abrirDetalle: async function (idContenedor) {
    const respuesta = await Api.llamarComoRol('GET', '/contenedor/data', { CONTENEDOR: { ID: idContenedor } });
    if (!respuesta.exito) {
      mostrarAviso(respuesta.mensaje, 'error');
      return;
    }

    let contenedor = respuesta.datos;
    if (respuesta.datos.CONTENEDOR) {
      contenedor = respuesta.datos.CONTENEDOR;
    }

    // Los historiales son largos: se muestran aparte, en secciones desplegables.
    const datosPrincipales = {};
    const historiales = {};
    for (const nombre of Object.keys(contenedor)) {
      if (nombre.startsWith('HISTORIAL') || nombre.startsWith('VACIADO')) {
        historiales[nombre] = contenedor[nombre];
      } else {
        datosPrincipales[nombre] = contenedor[nombre];
      }
    }

    const botones = crearElemento('div', { clase: 'row-btns', style: 'justify-content:flex-start' });
    botones.append(crearBoton('a.reportar', function () { AccionesContenedor.reportarTodo(idContenedor); }, 'btn'));
    if (rolActualEs('O')) {
      botones.append(crearBoton('a.vaciar', function () { AccionesContenedor.registrarVaciado(idContenedor); }, 'btn'));
    }

    const contenido = [construirDetalle(datosPrincipales), botones];
    for (const nombre of Object.keys(historiales)) {
      const resumen = crearElemento('summary', { texto: etiquetaDeCampo(nombre) });
      contenido.push(crearElemento('details', {}, resumen, construirDetalle(historiales[nombre])));
    }

    Modal.abrir(traducir('k.CONTENEDOR') + ' #' + idContenedor, contenido, { ancho: true });
  },

  // Qué condiciones y estados de basura puede reportar el rol actual.
  permisosDeEstado: function () {
    if (rolActualEs('V')) {
      // Un vecino solo puede reportar problemas, no marcar algo como normal o limpio.
      return { condiciones: ['Vandalizado', 'Roto', 'Quemado'], basuras: ['Basura_afuera'] };
    }
    if (rolActualEs('AO')) {
      return { condiciones: CONDICIONES_CONTENEDOR, basuras: ESTADOS_BASURA_CONTENEDOR };
    }
    // "Reparacion" solo lo puede marcar el admin operador.
    return { condiciones: ['Normal', 'Vandalizado', 'Roto', 'Quemado'], basuras: ESTADOS_BASURA_CONTENEDOR };
  },

  // Un solo formulario para incidente, estado y llenado. Lo que se deja vacío no se envía.
  // El llenado solo aparece para el operador de camión (el back no lo permite a otros roles).
  reportarTodo: function (idContenedor, alTerminar, opcionesDeContenedores) {
    const permisos = AccionesContenedor.permisosDeEstado();
    const puedeLlenado = rolActualEs('O');

    const gravedades = [{ valor: '', texto: traducir('none') }].concat(opcionesDeLista(GRAVEDADES_INCIDENTE));

    const campos = [
      { nombre: 'TIPO_INCIDENTE', etiqueta: 'k.TIPO_INCIDENTE', tipo: 'select', opciones: gravedades },
      { nombre: 'DESCRIPCION', etiqueta: 'k.DESCRIPCION', tipo: 'textoLargo' },
      { nombre: 'CONDICION', etiqueta: 'k.CONDICION', tipo: 'variasOpciones', opciones: opcionesDeLista(permisos.condiciones) },
      { nombre: 'BASURA', etiqueta: 'k.BASURA', tipo: 'variasOpciones', opciones: opcionesDeLista(permisos.basuras) }
    ];
    if (puedeLlenado) {
      campos.push({ nombre: 'LLENADO', etiqueta: 'k.LLENADO', tipo: 'numero', min: LLENADO_MINIMO, max: LLENADO_MAXIMO, ayuda: 'hint.llenado' });
    }
    campos.push({ nombre: 'FECHA', etiqueta: 'k.FECHA', ayuda: 'hint.fecha', ejemplo: '2026-10-31 14:30:00' });

    // Sin contenedor elegido (por ejemplo desde la pestaña de incidentes), se pide en el formulario.
    const sinContenedor = idContenedor === null || idContenedor === undefined;
    if (sinContenedor) {
      campos.unshift({ nombre: 'ID_CONTENEDOR', etiqueta: 'k.CONTENEDOR', tipo: 'select', opciones: opcionesDeContenedores, obligatorio: true });
    }

    abrirFormulario({
      titulo: traducir('a.reportar') + (sinContenedor ? '' : ' · #' + idContenedor),
      campos: campos,
      ancho: true,
      alTerminar: alTerminar,
      enviar: async function (valores) {
        const idFinal = sinContenedor ? Number(valores.ID_CONTENEDOR) : idContenedor;
        const hayIncidente = valores.TIPO_INCIDENTE !== undefined;
        const hayEstado = valores.CONDICION !== undefined || valores.BASURA !== undefined;
        const hayLlenado = valores.LLENADO !== undefined;

        if (!hayIncidente && !hayEstado && !hayLlenado) {
          return respuestaSinCambios();
        }
        if (!hayIncidente && valores.DESCRIPCION !== undefined) {
          return { exito: false, mensaje: traducir('req') + ': ' + traducir('k.TIPO_INCIDENTE') };
        }
        if (valores.FECHA !== undefined && !hayEstado && !hayLlenado) {
          return respuestaSinCambios();
        }

        // Se envía en orden; si uno falla se avisa qué ya quedó guardado para no repetirlo.
        const guardados = [];
        const pasos = [];

        if (hayIncidente) {
          const incidente = { ID_CONTENEDOR: idFinal, TIPO_INCIDENTE: valores.TIPO_INCIDENTE };
          if (valores.DESCRIPCION !== undefined) {
            incidente.DESCRIPCION = valores.DESCRIPCION;
          }
          pasos.push({ clave: 'a.incidente', llamar: function () {
            return Api.llamarComoRol('POST', '/contenedor/incidente', { INCIDENTE: incidente });
          } });
        }
        if (hayEstado) {
          const estado = {};
          if (valores.CONDICION !== undefined) { estado.CONDICION = valores.CONDICION; }
          if (valores.BASURA !== undefined) { estado.BASURA = valores.BASURA; }
          if (valores.FECHA !== undefined) { estado.FECHA = valores.FECHA; }
          pasos.push({ clave: 'a.estado', llamar: function () {
            return Api.llamarComoRol('POST', '/contenedor/estado', { CONTENEDOR: { ID: idFinal, ESTADO: estado } });
          } });
        }
        if (hayLlenado) {
          const contenedor = { ID: idFinal, LLENADO: Number(valores.LLENADO) };
          if (valores.FECHA !== undefined) { contenedor.FECHA = valores.FECHA; }
          pasos.push({ clave: 'a.llenado', llamar: function () {
            return Api.llamarComoRol('POST', '/contenedor/llenado', { CONTENEDOR: contenedor });
          } });
        }

        for (const paso of pasos) {
          const respuesta = await paso.llamar();
          if (!respuesta.exito) {
            let mensaje = traducir(paso.clave) + ': ' + respuesta.mensaje;
            if (guardados.length > 0) {
              mensaje += ' — ' + traducir('reporte.parcial') + ' ' + guardados.join(', ');
            }
            return { exito: false, mensaje: mensaje };
          }
          guardados.push(traducir(paso.clave));
        }
        return { exito: true, mensaje: traducir('ok') };
      }
    });
  },

  // Reportar la condición del contenedor (roto, vandalizado...) y/o la basura alrededor.
  reportarEstado: function (idContenedor, alTerminar) {
    const permisos = AccionesContenedor.permisosDeEstado();
    const condicionesPermitidas = permisos.condiciones;
    const estadosDeBasuraPermitidos = permisos.basuras;

    abrirFormulario({
      titulo: traducir('a.estado'),
      alTerminar: alTerminar,
      campos: [
        { nombre: 'CONDICION', etiqueta: 'k.CONDICION', tipo: 'variasOpciones', opciones: opcionesDeLista(condicionesPermitidas) },
        { nombre: 'BASURA', etiqueta: 'k.BASURA', tipo: 'variasOpciones', opciones: opcionesDeLista(estadosDeBasuraPermitidos) },
        { nombre: 'FECHA', etiqueta: 'k.FECHA', ayuda: 'hint.fecha', ejemplo: '2026-10-31 14:30:00' }
      ],
      enviar: function (valores) {
        if (Object.keys(valores).length === 0) {
          return respuestaSinCambios();
        }
        return Api.llamarComoRol('POST', '/contenedor/estado', {
          CONTENEDOR: { ID: idContenedor, ESTADO: valores }
        });
      }
    });
  },

  reportarIncidente: function (idContenedor, alTerminar) {
    abrirFormulario({
      titulo: traducir('a.incidente'),
      alTerminar: alTerminar,
      campos: [
        { nombre: 'TIPO_INCIDENTE', etiqueta: 'k.TIPO_INCIDENTE', tipo: 'select', opciones: opcionesDeLista(GRAVEDADES_INCIDENTE), obligatorio: true },
        { nombre: 'DESCRIPCION', etiqueta: 'k.DESCRIPCION', tipo: 'textoLargo' }
      ],
      enviar: function (valores) {
        const incidente = { ID_CONTENEDOR: idContenedor };
        incidente.TIPO_INCIDENTE = valores.TIPO_INCIDENTE;
        if (valores.DESCRIPCION) {
          incidente.DESCRIPCION = valores.DESCRIPCION;
        }
        return Api.llamarComoRol('POST', '/contenedor/incidente', { INCIDENTE: incidente });
      }
    });
  },

  // Solo el operador de camión informa cuánto se llenó un contenedor.
  informarLlenado: function (idContenedor, alTerminar) {
    abrirFormulario({
      titulo: traducir('a.llenado'),
      alTerminar: alTerminar,
      campos: [
        { nombre: 'LLENADO', etiqueta: 'k.LLENADO', tipo: 'numero', min: LLENADO_MINIMO, max: LLENADO_MAXIMO, obligatorio: true, ayuda: 'hint.llenado' },
        { nombre: 'FECHA', etiqueta: 'k.FECHA', ayuda: 'hint.fecha' }
      ],
      enviar: function (valores) {
        const contenedor = { ID: idContenedor, LLENADO: Number(valores.LLENADO) };
        if (valores.FECHA) {
          contenedor.FECHA = valores.FECHA;
        }
        return Api.llamarComoRol('POST', '/contenedor/llenado', { CONTENEDOR: contenedor });
      }
    });
  },

  // Devuelve la respuesta de la API (o null si la persona canceló), para que quien llama sepa si salió bien.
  registrarVaciado: async function (idContenedor) {
    return await confirmarYEjecutar(traducir('q.vaciar'), function () {
      return ejecutarYAvisar(Api.llamarComoRol('POST', '/contenedor/vaciado', { CONTENEDOR: { ID: idContenedor } }));
    });
  },

  // Crear (contenedorExistente = null) o editar un contenedor. Solo admin general.
  abrirFormularioEdicion: function (contenedorExistente, alTerminar) {
    const esNuevo = contenedorExistente === null;
    const actual = contenedorExistente || {};

    let enFuncion = true;
    if (!esNuevo) {
      enFuncion = esVerdadero(actual.EN_FUNCION);
    }

    abrirFormulario({
      titulo: traducir(esNuevo ? 'a.nuevo' : 'a.editar'),
      campos: [
        { nombre: 'UBICACION', etiqueta: 'k.UBICACION', tipo: 'ubicacion', valor: actual.UBICACION, obligatorio: true },
        { nombre: 'EN_FUNCION', etiqueta: 'k.EN_FUNCION', tipo: 'casilla', valor: enFuncion, incluirSiVacio: true },
        { nombre: 'TIPO_BASURAS', etiqueta: 'k.TIPO_BASURAS', tipo: 'variasOpciones', opciones: opcionesDeLista(TIPOS_BASURA), valor: actual.TIPO_BASURAS, obligatorio: true },
        { nombre: 'CANTIDAD', etiqueta: 'k.CANTIDAD', tipo: 'numero', min: 1, paso: 'any', valor: actual.CANTIDAD, obligatorio: true, ayuda: 'hint.litros' }
      ],
      enviar: function (valores) {
        const datos = {
          UBICACION: valores.UBICACION,
          EN_FUNCION: valores.EN_FUNCION,
          ALMACENADO: { TIPO_BASURAS: valores.TIPO_BASURAS, CANTIDAD: Number(valores.CANTIDAD) }
        };
        if (esNuevo) {
          return Api.llamarComoRol('POST', '/contenedor', { CONTENEDOR: datos });
        }
        datos.ID = actual.ID;
        return Api.llamarComoRol('PUT', '/contenedor', { CONTENEDOR: datos });
      },
      alTerminar: alTerminar
    });
  }
};
