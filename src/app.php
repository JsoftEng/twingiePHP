<?php

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

        try{
          $result = queryState($state);
        }catch(Exception $e){
          return "Error: " . $e->getMessage();
        }

        //echo $GLOBALS['g_marshaler']->marshalJson($result);

        return $response
          -> withStatus(200)
          -> withJson($result['Items']);
      }
  );

  $app->get('/analytics/senator',
      function(Request $request, Response $response, array $args){
        $twitterID = $request->getQueryParam('twitterid');
        $activity_by_interval = 'activity_by_interval';

        try{
          if (checkInterval()){
            $result = getAnalysis($twitterID);

            //$result = querySenator($twitterID);
          }else{
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
          //-> withJson($result->$activity_by_interval);
      }
  );

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
    return $result;
  }

  function querySenator($twitterID){
    //set up parametrs for querying senator db
    $params = [
      'TableName' => 'twingieSenators',
      'KeyConditionExpression' => 'senatorID = :v_hash',
      'ExpressionAttributeValues' => array(
          ':v_hash' => array('S' => $twitterID),
      )
    ];

    $result = $GLOBALS['g_client']->query($params);
    return $result;
  }

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

    $params = [
      'TableName' => 'twingieSenators',
      'Item' => array(
        $twitterID => $GLOBALS['g_marshaler']->marshalItem($analysis['activity_by_interval'])
      )
    ];

    //var_dump($GLOBALS['g_marshaler']->marshalItem($analysis['activity_by_interval']));

    //var_dump($GLOBALS['g_marshaler']->marshalItem($analysis));
    //$GLOBALS['g_client']->putItem($params);
  }

  function checkInterval(){
    $isWithinInterval = false;
    return $isWithinInterval;
  }

  function getAnalysis($senator){
    $most_popular_tweet = 'most_popular_tweet';
    $most_controversial_tweet = 'most_controversial_tweet';
    $related_hashtag = 'related_hashtag';
    $related_user = 'related_user';
    $volume_line_graph = 'volume_line_graph';
    $radar_graph = 'radar_graph';
    $stream_graph = 'stream_graph';
    $scatter_graph = 'scatter_graph';
    $pie_graph = 'pie_graph';

    $json = file_get_contents('http://twitter-user-evaluation-dev.us-east-1.elasticbeanstalk.com/?user='.$senator);
    $decodedJson = json_decode($json);

    $result = array(
      'most_popular_tweet' => $decodedJson->$most_popular_tweet,
      'most_controversial_tweet' => $decodedJson->$most_controversial_tweet,
      'related_hashtag' => $decodedJson->$related_hashtag,
      'related_user' => $decodedJson->$related_user,
      'volume_line_graph' => $decodedJson->$volume_line_graph,
      'radar_graph' => $decodedJson->$radar_graph,
      'stream_graph' => $decodedJson->$stream_graph,
      'scatter_graph' => $decodedJson->$scatter_graph,
      'pie_graph' => $decodedJson->$pie_graph,
    );

    return $result;
  }

  $app->run();
