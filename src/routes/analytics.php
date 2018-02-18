<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//get user analytics
$app->get('/api/analytics/user/{userid}',
    function(Request $request, Response $response, array $args){
      //query ml api
      $userID = $args['userid'];
      $response->getBody()->write($userID);
      $mlAnalysis = json_decode(file_get_contents('http://mlapiplchldr/?user={$userID}'),true);

      //TODO - implement logic for user analytics
    }
);

//get tweet analytics
$app->get('/api/analytics/hashtag/{context}',
    function(Request $request, Response $response, array $args){
      //query ml api
      $context = $args['context'];
      $analysisIn = json_decode(file_get_contents('http://mlapiplchldr/?hashtag={$context}'),true);
      $analysisOut = array(
        'strength' => $analysisIn['strength']
        'text' => $analysisIn['text']
      );

      $sql = "INSERT INTO analysisdump (analysis_strength,analysis_text) VALUES
        (:analysisStrength,:analysisText)";

      try{
        //get db object
        $db = new db();
        //connect to db
        $db = $db->connect();
        //execute sql statement
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':analysisStrength',$analysisOut['strength']);
        $stmt->bindParam(':analysisText',$analysisOut['text']);
        $stmt->execute();

      } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}}';
      }

      return json_encode($analysisOut);
    }
);

//add user analytics
$app->post('/api/analytics/add',
    function(Request $request, Response $response){
      $strength = $request->getParam('strength');
      $text = $request->getParam('text');

      $sql = "INSERT INTO analysisdump (analysis_strength,analysis_text) VALUES
        (:analysisStrength,:analysisText)";

      try{
        //get db object
        $db = new db();
        //connect to db
        $db = $db->connect();

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':analysisStrength',$strength);
        $stmt->bindParam(':analysisText',$text);

        $stmt->execute();

        echo '{"notice": {"text": "Analytics added"}';
      } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
      }
    }
);
