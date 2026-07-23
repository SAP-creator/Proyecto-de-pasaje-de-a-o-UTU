<?php class respuestaHTTP{
    private int $http_respuesta;
    private string $json_respuesta;

    public function __construct(int $http, string $json = ""){
        $this->http_respuesta = $http; 
        $this->json_respuesta = $json;}
    
    public function enviar(){
        http_response_code($this->http_respuesta);
        echo $this->json_respuesta;
    }
}
?>