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

  // Setup marshaler for JSON
  $g_marshaler = new Marshaler;

  // Enable CORS
  $app->options('/{routes:.+}', function ($request, $response, $args) {
      return $response;
  });

  $app->add(function ($req, $res, $next) {
      $response = $next($req, $res);
      return $response
              ->withHeader('Access-Control-Allow-Origin','*')
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

        if ($result != null){
          return $response
            -> withStatus(200)
            -> withJson($result);
        }else{
          return $response
            -> withStatus(400)
            -> write("State does not exist!");
        }
      }
  );

  // Handle senator analysis request
  $app->get('/analytics/senator',
      function(Request $request, Response $response, array $args){
        $state = $request->getQueryParam('state');
        $twitterID = $request->getQueryParam('twitterid');
        $last_updated = 'last_updated';

        if(validateSenator($state,$twitterID)){
          $result = querySenator($twitterID);
        }else{
          return $response
            -> withStatus(400)
            -> write("Senator does not exist!");
        }

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
    //check if state exists in db
    if($result['Count'] == 0){
      return null;
    }

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
    //check if senator exists in db
    if($result['Count'] == 0){
      $analysis = getAnalysis($twitterID);
      depositAnalysis($analysis,$twitterID);
      $result = $GLOBALS['g_client']->query($params);
    }

    $reformatedJSON = json_decode($GLOBALS['g_marshaler']->unmarshalJson($result['Items'][0]));

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
    $depositTime = time();

    $data = array(
      'senatorID' => $twitterID,
      'related_hashtag' => $analysis->related_hashtag,
      'related_user' => $analysis->related_user,
      'named_entity_bar_graph' => $analysis->named_entity_bar_graph,
      'volume_line_graph' => $analysis->volume_line_graph,
      'scatter_graph' => $analysis->scatter_graph,
      'last_updated' => $depositTime
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
  * Checks whether specified senator is valid
  *
  * @param string $senator senator to be validated
  *
  * @author John Johnson <jsofteng@gmail.com>
  * @return boolean false if senator does not exist in state db (doesn't belong to any state)
  **/
  function validateSenator($state,$twitterID){
    $isValid = false;

    $params = [
      'TableName' => 'twingieStates',
      'KeyConditionExpression' => 'stateABV = :v_hash',
      'ExpressionAttributeValues' => array(
          ':v_hash' => array('S' => $state),
      )
    ];

    $queryResult = $GLOBALS['g_client']->query($params)['Items'];
    $queryArray = json_decode($GLOBALS['g_marshaler']->unmarshalJson($queryResult[0]), TRUE);
    $senators = $queryArray['senators'];
    $senatorKeys = array(
      'senator_1' => array_keys($senators[0])[0],
      'senator_2' => array_keys($senators[1])[0]
    );

    $senator_1 = $senators[0][$senatorKeys['senator_1']]['twitterID'];
    $senator_2 = $senators[1][$senatorKeys['senator_2']]['twitterID'];

    if(strtoupper($senator_1) == strtoupper(('@'.$twitterID)) || strtoupper($senator_2) == strtoupper(('@'.$twitterID))){
      $isValid = true;
    }

    return $isValid;
  }

  /**
  * retrieves analysis data from twitter user evaluation service
  *
  * @param string $senator senator twitter id
  *
  * @author John Johnson <jsofteng@gmail.com>
  * @return JSON
  **/
  function getAnalysis($twitterID){
    $json = file_get_contents('http://twitter-user-evaluation-dev.us-east-1.elasticbeanstalk.com/?user='.$twitterID);
    $decodedJson = json_decode($json);

    return $decodedJson;
  }

  $app->run();
