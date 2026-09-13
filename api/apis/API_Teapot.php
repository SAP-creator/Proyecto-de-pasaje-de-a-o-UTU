<?php 

include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";

Util_HttpResponse::error(
    Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, "TEAPOT", "TEAPOT"),
    418)->send();
