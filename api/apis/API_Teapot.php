<?php 

include_once __DIR__ . "/../utils/resp_http.php";

HttpResponse::error("Sorry im am a teapot", 418)->send();

