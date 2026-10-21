<?php
// Const_Paths.php

const path_api = "/GIT/api";
const path_api_user = path_api . "/user";
const path_api_sysadmin = path_api_user . "/adminsys";

// APIs por rol
const path_api_vecino = path_api_user . "/vecino";
const path_api_operador = path_api_user . "/operador";
const path_api_admin_operador = path_api_user . "/adminoperador";
const path_api_admin_general = path_api_user . "/admingeneral";

const path_api_contenedor = path_api . "/contenedor";
const path_api_contenedor_data = path_api_contenedor . "/data";
const path_api_contenedor_estado = path_api_contenedor . "/estado";
const path_api_contenedor_llenado = path_api_contenedor . "/llenado";
const path_api_contenedor_vaciado = path_api_contenedor . "/vaciado";
const path_api_contenedor_incidente = path_api_contenedor . "/incidente";

const path_api_centro = path_api . "/centro";
const path_api_centro_data = path_api_centro . "/data";
const path_api_centro_retiro = path_api_centro . "/retiro";

const path_api_ruta = path_api . "/ruta";
const path_api_ruta_data = path_api_ruta . "/data";
const path_api_ruta_contenedor = path_api_ruta . "/contenedor";
