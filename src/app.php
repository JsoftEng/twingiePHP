<?php

  require __DIR__ . '/../vendor/autoload.php';

  use \Psr\Http\Message\ServerRequestInterface as Request;
  use \Psr\Http\Message\ResponseInterface as Response;
  use Aws\Common\Aws;

  // Setup Application
  $app = new \Slim\App;

  // Setup Database
  $g_aws = Aws::factory(__DIR__ . '/../config.php');
  $g_client = $g_aws->get('DynamoDb');

  // Enable CORS
  $app->options('/{routes:.+}', function ($request, $response, $args) {
      return $response;
  });

  $app->add(function ($req, $res, $next) {
      $response = $next($req, $res);
      return $response
              ->withHeader('Access-Control-Allow-Origin', 'http://s3storage-twingie-testbucket.s3-website.us-east-2.amazonaws.com')
              ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
              ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
  });

  // Handle the hashtag request
  $app->get('/',
      function(Request $request, Response $response, array $args){
          return $response
            -> withStatus(200)
            -> write("TwingiePHP Root Directory");
      }
  );

  $app->get('/analytics/state',
      function(Request $request, Response $response, array $args){
        $state = $request->getQueryParam('state');

        //set up parametrs for db query
        $params = [
          'TableName' => 'twingieStates',
          'KeyConditionExpression' => 'stateABV = :v_hash',
          'ExpressionAttributeValues' => array(
              ':v_hash' => array('S' => $state),
          )
        ];

        //query db
        try{
          $result = $GLOBALS['g_client']->query($params);
        } catch(Exception $e){
              return "Error: " . $e->getMessage();
        }

        return $response
          -> withStatus(200)
          -> withJson($result['Items']);
      }
  );

  $app->get('/analytics/senator',
      function(Request $request, Response $response, array $args){
        $twitterID = $request->getQueryParam('twitterid');

        //set up parametrs for db query
        $params = [
          'TableName' => 'twingieSenators',
          'KeyConditionExpression' => 'senatorID = :v_hash',
          'ExpressionAttributeValues' => array(
              ':v_hash' => array('S' => $twitterID),
          )
        ];

        //query db
        try{
          $result = $GLOBALS['g_client']->query($params);
        } catch(Exception $e){
              return "Error: " . $e->getMessage();
        }

        return $response
          -> withStatus(200)
          -> withJson($result['Items']);
      }
  );

  $app->run();
