<?php 

include_once __DIR__ . "/../utils/resp_http.php";

Util_HttpResponse::error(418,"Sorry im am a teapot")->send();

