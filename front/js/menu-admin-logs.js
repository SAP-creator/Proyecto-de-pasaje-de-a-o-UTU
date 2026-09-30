/**
 * menu-admin-logs.js
 * Pinta el menú "Logs" de admin sistema, con sus dos sub-tabs
 * (logs de usuario / logs de base de datos) y los filtros de la pestaña
 * de logs de usuario (tipo de usuario, tipo de evento, cédula y fecha).
 *
 * El backend filtra por tipo de usuario y tipo de evento server-side
 * (TYPEUSER / TYPELOG); cédula y fecha se filtran acá porque el endpoint
 * no los soporta todavía.
 */

const LISTA_MAPEO_LOGS = {
  'Error C ApiAdminSys GetUsersLogsError': 'No se pudieron obtener los logs de usuarios.',
  'Error C ApiAdminSys GetSqlLogsError': 'No se pudieron obtener los logs de base de datos.'
};

const MenuAdminLogs = {
  _logsUsuarios: [],

  async iniciar() {
    Tabs.armarSubTabs(logs_subtabs, [
      { texto: 'Menu logs user', idPanel: 'logs_panel_usuarios' },
      { texto: 'Menu BD', idPanel: 'logs_panel_bd' }
    ]);

    this._llenarFiltroTipoUsuario();

    logs_filtro_tipo_usuario.addEventListener('change', () => this._cargarLogsUsuarios());
    logs_filtro_tipo_evento.addEventListener('change', () => this._cargarLogsUsuarios());
    logs_filtro_cedula.addEventListener('input', () => this._pintarLogsUsuarios());
    logs_filtro_fecha.addEventListener('change', () => this._pintarLogsUsuarios());

    await Promise.all([this._cargarLogsUsuarios(), this._cargarLogsBd()]);
  },

  _llenarFiltroTipoUsuario() {
    logs_filtro_tipo_usuario.replaceChildren();
    const todos = document.createElement('option');
    todos.value = '';
    todos.textContent = 'Todos';
    logs_filtro_tipo_usuario.appendChild(todos);
    CONFIG.TIPOS_USUARIO.forEach(t => {
      const opcion = document.createElement('option');
      opcion.value = t.valor;
      opcion.textContent = t.etiqueta;
      logs_filtro_tipo_usuario.appendChild(opcion);
    });
  },

  async _cargarLogsUsuarios() {
    logs_usuarios_tabla_vacia.classList.add('hidden');

    const resultado = await ApiCliente.fetchDatos('LOGS_USERS', {
      TOKEN: auth.tokenUsuario(),
      TYPEUSER: logs_filtro_tipo_usuario.value || undefined,
      TYPELOG: logs_filtro_tipo_evento.value.trim() || undefined
    }, LISTA_MAPEO_LOGS);

    if (resultado.esError) {
      logs_usuarios_tabla_cuerpo.replaceChildren();
      logs_usuarios_tabla_vacia.textContent = resultado.mensajeUsuario;
      logs_usuarios_tabla_vacia.classList.remove('hidden');
      this._logsUsuarios = [];
      return;
    }

    const lista = resultado.datos || [];
    this._logsUsuarios = Array.isArray(lista) ? lista : [];
    this._pintarLogsUsuarios();
  },

  _pintarLogsUsuarios() {
    logs_usuarios_tabla_cuerpo.replaceChildren();

    const textoCedula = logs_filtro_cedula.value.trim();
    const fecha = logs_filtro_fecha.value;

    const filtrados = this._logsUsuarios.filter((log) => {
      const cedula = log.CEDULA_USUARIO ?? log.CI ?? '';
      if (textoCedula && !String(cedula).includes(textoCedula)) return false;
      if (fecha && !String(log.FECHA || '').startsWith(fecha)) return false;
      return true;
    });

    if (!filtrados.length) {
      logs_usuarios_tabla_vacia.textContent = 'No hay eventos para mostrar.';
      logs_usuarios_tabla_vacia.classList.remove('hidden');
      return;
    }
    logs_usuarios_tabla_vacia.classList.add('hidden');

    filtrados.forEach((log) => {
      const fila = tpl_fila_log_usuario.content.cloneNode(true);
      fila.querySelector('[data-campo="fecha"]').textContent = log.FECHA;
      fila.querySelector('[data-campo="ci"]').textContent = log.CEDULA_USUARIO ?? log.CI ?? '—';
      fila.querySelector('[data-campo="tipo"]').textContent = log.TYPELOG;
      fila.querySelector('[data-campo="texto"]').textContent = log.TEXTO;
      logs_usuarios_tabla_cuerpo.appendChild(fila);
    });
  },

  async _cargarLogsBd() {
    logs_bd_tabla_cuerpo.replaceChildren();
    logs_bd_tabla_vacia.classList.add('hidden');

    const resultado = await ApiCliente.fetchDatos('LOGS_SQL', {
      TOKEN: auth.tokenUsuario()
    }, LISTA_MAPEO_LOGS);

    if (resultado.esError) {
      logs_bd_tabla_vacia.textContent = resultado.mensajeUsuario;
      logs_bd_tabla_vacia.classList.remove('hidden');
      return;
    }

    const lista = resultado.datos || [];
    if (!lista.length) {
      logs_bd_tabla_vacia.textContent = 'No hay eventos para mostrar.';
      logs_bd_tabla_vacia.classList.remove('hidden');
      return;
    }

    lista.forEach((log) => {
      const fila = tpl_fila_log_sql.content.cloneNode(true);
      fila.querySelector('[data-campo="fecha"]').textContent = log.FECHA;
      fila.querySelector('[data-campo="modelo"]').textContent = log.TIPO_MODELO;
      fila.querySelector('[data-campo="texto"]').textContent = log.TEXTO;

      // Columnas nuevas del backend (query ejecutada y sus parámetros).
      // El backend todavía no las traduce a mayúsculas, así que se leen
      // en minúscula; se pintan solo si el template ya tiene la celda.
      const celdaQuery = fila.querySelector('[data-campo="query"]');
      if (celdaQuery) celdaQuery.textContent = log.query ?? log.QUERY ?? '';

      const celdaParametros = fila.querySelector('[data-campo="parametros"]');
      if (celdaParametros) celdaParametros.textContent = log.parametros ?? log.PARAMETROS ?? '';

      logs_bd_tabla_cuerpo.appendChild(fila);
    });
  }
};
