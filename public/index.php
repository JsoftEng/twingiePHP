<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
//db connection properties
require '../src/config/db.php';

$app = new \Slim\App;

//analytics routing
require '../src/routes/analytics.php';

$app->run();
