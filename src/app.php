<?php
 /**
  * The following serves as the backend for a twitter analysis web service.
  *
  * PHP version 5.6
  *
  * LICENSE: This source file is subject to version 3.01 of the PHP license
  * that is available through the world-wide-web at the following URI:
  * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
  * the PHP License and are unable to obtain it through the web, please
  * send a note to license@php.net so we can mail you a copy immediately.
  *
  * @author     John Johnson <jsofteng@gmail.com>
  **/

  require __DIR__ . '/../vendor/autoload.php';

  use \Psr\Http\Message\ServerRequestInterface as Request;
  use \Psr\Http\Message\ResponseInterface as Response;
  use Aws\Common\Aws;
  use Aws\DynamoDb\Marshaler;

  // Setup Application
  $app = new \Slim\App;

  // Setup Database
  $g_aws = Aws::factory(__DIR__ . '/../config.php');
  $g_client = $g_aws->get('DynamoDb');
  $g_marshaler = new Marshaler;

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

  // Landing page
  $app->get('/',
      function(Request $request, Response $response, array $args){
          return $response
            -> withStatus(200)
            -> write("TwingiePHP Root Directory");
      }
  );

  // Handle state senator request
  $app->get('/analytics/state',
      function(Request $request, Response $response, array $args){
        $state = $request->getQueryParam('state');

        try{
          $result = queryState($state);
        }catch(Exception $e){
          return "Error: " . $e->getMessage();
        }

        $depositTime = time();

        return $response
          -> withStatus(200)
          -> withJson($result);
      }
  );

  // Handle senator analysis request
  $app->get('/analytics/senator',
      function(Request $request, Response $response, array $args){
        $twitterID = $request->getQueryParam('twitterid');
        $last_updated = 'last_updated';
        $result = querySenator($twitterID);

        try{
          // check if data was updated in last 24 hours
          if (!checkInterval($result->$last_updated)){
            $analysisResult = getAnalysis($twitterID);
            depositAnalysis($analysisResult, $twitterID);
            $result = querySenator($twitterID);
          }
        }catch(Exception $e){
          return "Error: " . $e->getMessage();
        }

        return $response
          -> withStatus(200)
          -> withJson($result);
      }
  );

  /**
  * Queries db and returns senators for state as an associative array
  *
  * @param string $state abbreviation for state
  *
  * @author John Johnson <jsofteng@gmail.com>
  * @return array
  **/
  function queryState($state){
    //set up parametrs for querying state db
    $params = [
      'TableName' => 'twingieStates',
      'KeyConditionExpression' => 'stateABV = :v_hash',
      'ExpressionAttributeValues' => array(
          ':v_hash' => array('S' => $state),
      )
    ];

    //query db
    $result = $GLOBALS['g_client']->query($params);
    $reformatedJSON = json_decode($GLOBALS['g_marshaler']->unmarshalJson($result['Items'][0]));

    return $reformatedJSON;
  }

  /**
  * Queries db and returns senator analysis data as an associative array
  *
  * @param string $twitterID senator twitter id
  *
  * @author John Johnson <jsofteng@gmail.com>
  * @return array
  **/
  function querySenator($twitterID){
    //set up parameters for querying senator db
    $params = [
      'TableName' => 'twingieSenators',
      'KeyConditionExpression' => 'senatorID = :v_hash',
      'ExpressionAttributeValues' => array(
          ':v_hash' => array('S' => $twitterID),
      )
    ];

    $result = $GLOBALS['g_client']->query($params);
    $reformatedJSON = json_decode($GLOBALS['g_marshaler']->unmarshalJson($result['Items'][0]));
    //var_dump(json_decode($GLOBALS['g_marshaler']->unmarshalJson($result['Items'][0])));

    return $reformatedJSON;
  }

 /**
 * Deposits JSON data into dynamodb senator table if last deposit was outside 24 hour interval
 *
 * @param JSON   $analysis  Analysis returned by twitter user evaluation service
 * @param string $twitterID senator twitter id
 *
 * @author John Johnson <jsofteng@gmail.com>
 * @return void
 **/
  function depositAnalysis($analysis, $twitterID){
    $most_popular_tweet = 'most_popular_tweet';
    $most_controversial_tweet = 'most_controversial_tweet';
    $related_hashtag = 'related_hashtag';
    $related_user = 'related_user';
    $volume_line_graph = 'volume_line_graph';
    $radar_graph = 'radar_graph';
    $stream_graph = 'stream_graph';
    $scatter_graph = 'scatter_graph';
    $pie_graph = 'pie_graph';

    $depositTime = time();

    $data = array(
      'senatorID' => $twitterID,
      'most_popular_tweet' => $analysis->$most_popular_tweet,
      'most_controversial_tweet' => $analysis->$most_controversial_tweet,
      'related_hashtag' => $analysis->$related_hashtag,
      'related_user' => $analysis->$related_user,
      'volume_line_graph' => $analysis->$volume_line_graph,
      'radar_graph' => $analysis->$radar_graph,
      'stream_graph' => $analysis->$stream_graph,
      'scatter_graph' => $analysis->$scatter_graph,
      'last_updated' => $depositTime
      //'pie_graph' => $analysis->$pie_graph
    );

    $params = [
      'TableName' => 'twingieSenators',
      'Item' => $GLOBALS['g_marshaler']->marshalItem($data)
    ];

    $GLOBALS['g_client']->putItem($params);
  }

  /**
  * Checks whether last data deposit was within 24 hours
  *
  * @param integer $depositTime  Last deposit time in unix time
  *
  * @author John Johnson <jsofteng@gmail.com>
  * @return boolean false if outside 24 hour interval
  **/
  function checkInterval($depositTime){
    $depositInterval = ((Time() - $depositTime)/3600) % 24;
    $isWithinInterval = true;

    if ($depositInterval > 24){
      $isWithinInterval = false;
    }

    return $isWithinInterval;
  }

  /**
  * retrieves analysis data from twitter user evaluation service
  *
  * @param string $senator senator twitter id
  *
  * @author John Johnson <jsofteng@gmail.com>
  * @return JSON
  **/
  function getAnalysis($senator){
    $json = file_get_contents('http://twitter-user-evaluation-dev.us-east-1.elasticbeanstalk.com/?user='.$senator);
    $decodedJson = json_decode($json);

    return $decodedJson;
  }

  $app->run();
