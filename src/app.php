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

// Handle the hashtag request
$app->get('/analytics/hashtag/{context}',
    function(Request $request, Response $response, array $args){
      //query db
      $context = $args['context'];
      $nowDate = time();
      $yesterdayDate = strtotime('-1 day', $nowDate);
      $params = [
        'TableName' => 'frameworks',
        'Limit' => 60,
        'KeyConditionExpression' => 'framework = :v_hash and unix_time between :v_range1 and :v_range2 ',
        'ExpressionAttributeValues' => array(
            ':v_hash' => array('S' => $context),
            ':v_range1' => array('N' => $yesterdayDate),
            ':v_range2' => array('N' => $nowDate)
        )
      ];
      try{
        $result = $GLOBALS['g_client']->query($params);
      } catch(Exception $e){
          if (strcmp($e->getMessage(),"Requested resource not found") == 0){
            return "Spin up another listener for " . $context . "!";
          } else{
              return "Error: " . $e->getMessage();
            }
      }

      return $response
        -> withStatus(200)
        -> withJson($result['Items']);
    }
);

$app->run();
