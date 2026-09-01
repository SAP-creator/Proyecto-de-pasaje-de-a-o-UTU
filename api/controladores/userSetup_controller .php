<?php

class UserSetupController{

    #si el usuario dentro del la base de datos esta completo entonces deja seguir, en el caso contrario envia un HttpResponse y hace un die
    #PREPARE THY SELF #me lo dice un rey a cada rato
    public static function user_is_complete(array $data){

    }

    #completa el usuario con toda la informacion que le falta. Rellena todas las que puede pero tira un warning si falta alguna (null)
    #cuando todos los datos del usuario (no son null) entonces cambia datos completos a true.
    public static function complet_user(array $data){

    }

}