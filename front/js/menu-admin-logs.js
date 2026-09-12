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
    try {
      const lista = await api.obtenerLogsUsuarios(auth.tokenAdmin());
      if (!lista || !lista.length) {
        logs_usuarios_tabla_vacia.textContent = 'No hay eventos para mostrar.';
        logs_usuarios_tabla_vacia.classList.remove('hidden');
        return;
      }
      lista.forEach((log) => {
        const fila = tpl_fila_log_usuario.content.cloneNode(true);
        fila.querySelector('[data-campo="fecha"]').textContent = log.fecha;
        fila.querySelector('[data-campo="ci"]').textContent = log.CI;
        fila.querySelector('[data-campo="tipo"]').textContent = log.TYPELOG;
        fila.querySelector('[data-campo="texto"]').textContent = log.texto;
        logs_usuarios_tabla_cuerpo.appendChild(fila);
      });
    } catch (error) {
      logs_usuarios_tabla_vacia.textContent = error.message || 'No se pudieron cargar los logs.';
      logs_usuarios_tabla_vacia.classList.remove('hidden');
    }
  },

  async _cargarLogsBd() {
    logs_bd_tabla_cuerpo.replaceChildren();
    logs_bd_tabla_vacia.classList.add('hidden');
    try {
      const lista = await api.obtenerLogsSql(auth.tokenAdmin());
      if (!lista || !lista.length) {
        logs_bd_tabla_vacia.textContent = 'No hay eventos para mostrar.';
        logs_bd_tabla_vacia.classList.remove('hidden');
        return;
      }
      lista.forEach((log) => {
        const fila = tpl_fila_log_sql.content.cloneNode(true);
        fila.querySelector('[data-campo="fecha"]').textContent = log.fecha;
        fila.querySelector('[data-campo="modelo"]').textContent = log.tipo_modelo;
        fila.querySelector('[data-campo="texto"]').textContent = log.texto;
        logs_bd_tabla_cuerpo.appendChild(fila);
      });
    } catch (error) {
      logs_bd_tabla_vacia.textContent = error.message || 'No se pudieron cargar los logs.';
      logs_bd_tabla_vacia.classList.remove('hidden');
    }
  }
};
