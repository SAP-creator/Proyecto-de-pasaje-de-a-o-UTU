<?php 
echo "A";
include_once __DIR__ . "/../utils/Util_RestHttp.php";

Util_HttpResponse::error(418,"Sorry im am a teapot")->send();

