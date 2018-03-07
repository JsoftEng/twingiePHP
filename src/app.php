<?php

require __DIR__ . '/../vendor/autoload.php';

//use Silex\Application;
//use Silex\Provider\TwigServiceProvider;
//use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\HttpFoundation\Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Aws\Common\Aws;

// Setup Application
$app = new \Slim\App;

// Setup Database
$g_aws = Aws::factory(__DIR__ . '/../config.php');
$g_client = $g_aws->get('DynamoDb');

// Handle the hashtag request
$app->get('/analytics/hashtag/{context}',
    function(Request $request, Response $response, array $args){
      //query db
      $context = $args['context'];
      try{
        $result = $GLOBALS['g_client']->scan(array(
          'TableName' => $context,
          'Limit' => 5
        ));
      } catch(Exception $e){
          if (strcmp($e->getMessage(),"Requested resource not found") == 0){
            return "Spin up another listener for " . $context . "!";
          } else{
              return "Error: " . $e->getMessage();
            }
      }

      return json_encode($result['Items']);
    }
);

$app->run();
