<?php

header("Access-Control-Allow-Origin: http://s3storage-twingie-testbucket.s3-website.us-east-2.amazonaws.com");

error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/../src/app.php';
