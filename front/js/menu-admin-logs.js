/**
 * menu-admin-logs.js
 * Única función: pintar el menú "Logs" de admin sistema, con sus dos
 * sub-tabs (Menu logs user / Menu BD).
 *
 * OJO: en tu mockup la pestaña "Menu BD" mostraba columnas fecha/CI/texto,
 * pero el endpoint real /user/adminsys/logs/sql no trae CI (trae id, fecha,
 * tipo_modelo, texto). Lo armé según lo que ese endpoint realmente devuelve;
 * avisame si en verdad esa pestaña necesita otra cosa.
 */

const LISTA_MAPEO_LOGS = {
  'Error C ApiAdminSys GetUsersLogsError': 'No se pudieron obtener los logs de usuarios.',
  'Error C ApiAdminSys GetSqlLogsError': 'No se pudieron obtener los logs de base de datos.'
};

const MenuAdminLogs = {
  async iniciar() {
    Tabs.armarSubTabs(logs_subtabs, [
      { texto: 'Menu logs user', idPanel: 'logs_panel_usuarios' },
      { texto: 'Menu BD', idPanel: 'logs_panel_bd' }
    ]);

    await Promise.all([this._cargarLogsUsuarios(), this._cargarLogsBd()]);
  },


  async _cargarLogsUsuarios() {
    logs_usuarios_tabla_cuerpo.replaceChildren();
    logs_usuarios_tabla_vacia.classList.add('hidden');

    const resultado = await ApiCliente.fetchDatos('LOGS_USERS', {
      TOKEN: auth.tokenUsuario()
    }, LISTA_MAPEO_LOGS);

    if (resultado.esError) {
      logs_usuarios_tabla_vacia.textContent = resultado.mensajeUsuario;
      logs_usuarios_tabla_vacia.classList.remove('hidden');
      return;
    }
    
    

    // Dependiendo de si los datos están en resultado.datos, resultado.logs, 
    // o si el array viene en otra propiedad, ajustamos esto:
    const lista = resultado.datos || resultado.logs || []; 
    
    if (!Array.isArray(lista)) {
      console.error("El servidor no devolvió un array:", lista);
      return;
    }

    lista.forEach((log) => {
      const fila = tpl_fila_log_usuario.content.cloneNode(true);
      fila.querySelector('[data-campo="fecha"]').textContent = log.FECHA;
      fila.querySelector('[data-campo="ci"]').textContent = log.CI;
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
      logs_bd_tabla_cuerpo.appendChild(fila);
    });
  }
};
