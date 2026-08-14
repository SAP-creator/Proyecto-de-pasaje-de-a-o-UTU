<?php


class UserModel{

    #-- retorna:
    #array - lo consiguio (aunque este vacio)
    #null - error en la query
    public static function get_users(string $type): ?array{
        return null;
    }

    #-- retorna:
    #array - lo consiguio (aunque este vacio)
    #null - error en la query
    public static function get_request_users(string $type): ?array{
        return null;
    }

    #-- retorna:
    #true - lo consiguio
    #false - no lo consiguio
    #null - error en la query
    public static function get_user(int $ci): ?bool{
        return null;
    }

    #-- retorna:
    #true - lo consiguio
    #false - no lo consiguio
    #null - error en la query
    public static function get_request_user(int $ci): ?bool{
        return null;
    }

    #-- retorna:
    #true - lo consiguio
    #false - no lo consiguio
    #null - error en la query
    public static function accept_request_user(int $ci): ?bool{
        return null;
    }

    

}