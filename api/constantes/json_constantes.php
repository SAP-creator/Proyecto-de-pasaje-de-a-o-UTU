<?php

#Siempre que se quiera hablar de un atributo de un usuario, ya sea
#Solicitud o Usuario normal
#En el json siempre va a adentro de un json.
#Incluso en las firmas.
const key_user = "USER";

#Que tipo de usuario es. O va a ser
const key_typeuser = "TYPEUSER";
#Cedula
const key_ci = "CI";
#Contraseña del usuario
const key_password = "PASSWORD";

const key_completeuser = "COMPLETEUSER";

#Token (implementacion a medias de JWS/JWV)
const key_token = "TOKEN";
#Firma de token
const key_token_sig = "SIGNATURE";



#tu! (nota eliminar para las entregas)
#error, eso no mas.
const key_error = "ERROR";

const key_typelog = "TYPELOG";