<?php

require __DIR__ . '/../vendor/autoload.php';
//require __DIR__ . '/db-connect.php';

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Aws\DynamoDb\Exception\DynamoDbException;

// Setup the application
$app = new Application();
$app['debug'] = true;
$app->register(new TwigServiceProvider, array(
    'twig.path' => __DIR__ . '/templates',
));

// Setup the database
$sdk = new Aws\Sdk([
  'endpoint' => 'dynamodb endpoint',
  'region' => 'us-west-2',
  'version' => 'latest'
]);

$dynamodb = $sdk->createDynamoDB();

//$app['db.hashtagTable'] = DB_HASHTAGTABLE;
//$app['db.userTable'] = DB_USERTABLE;
//$app['db.dsn'] = 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST;
//$app['db'] = $app->share(function ($app) {
//    return new PDO($app['db.dsn'], DB_USER, DB_PASSWORD);
//});

$app->error(function (\PDOException $e, $code) use ($app) {
  $alert = array('type' => 'error', 'message' => 'Can\'t connect to database. If you launched this application with AWS Elastic Beanstalk, please go to your Environment\'s Configuration page in the AWS Management Console and click \'create a new RDS database\' in the Data Tier section.');
  return $app['twig']->render('index.twig', array(
      'alert'    => $alert
  ));
});

// Handle the index page
$app->match('/', function () use ($app) {
    $query = $app['db']->prepare("SELECT analyticsText, hashtag FROM {$app['db.hashtagTable']}");
    $hashtagAnalytics = $query->execute() ? $query->fetchAll(PDO::FETCH_ASSOC) : array();

    $query = $app['db']->prepare("SELECT analyticsText, user FROM {$app['db.userTable']}");
    $userAnalytics = $query->execute() ? $query->fetchAll(PDO::FETCH_ASSOC) : array();

    return $app['twig']->render('index.twig', array(
        'title'    => 'Twingie Analytics',
        'hashtagAnalytics' => $hashtagAnalytics,
        'userAnalytics' => $userAnalytics
    ));
});

//Handle test page
$app->match('/dyna', function () use ($app) {
    $params = [

    ];
    $result = $dynamodb->getItem([
      'Key' => ''
      'TableName' =>
    ])
    $dynamodb->
    return $app['twig']->render('dyna.twig', array(
        'title'    => 'Twingie Analytics',
        'userAnalytics' => $userAnalytics
    ));
});

// Handle the hashtag request
$app->match('/hashtag', function (Request $request) use ($app) {
    $alert = null;
    // If the form was submitted, process the input
    if ('GET' == $request->getMethod()) {
        try {
            // Make sure the photo was uploaded without error
            $criteria = $request->request->get('criteria');
            if ($criteria < 64) {
                // Access ML API for analysis data
                $analysisIn = json_decode(file_get_contents('http://twingiems-env-1.9tmf8mcyqx.us-east-2.elasticbeanstalk.com/?hashtag={$criteria}'),true);
                $analysisOut = array(
                  "mct" => $analysisIn["most_controversial_tweet"],
                  "mpt" => $analysisIn["most_popular_tweet"],
                  "rel" => $analysisIn["related_hashtag"]
                );

                // Insert analysis data into DB
                foreach ($analysisOut as $value){
                  $sql = "INSERT INTO {$app['db.hashtagTable']} (analysisText, hashtag) VALUES (:analysisText, :hashtag)";
                  $query = $app['db']->prepare($sql);
                  $data = array(
                      ':analysisText' => $value,
                      ':hashtag'  => $criteria,
                  );
                }
                if (!$query->execute($data)) {
                    throw new \RuntimeException('Transfer of analysis to the database failed.');
                }
            } else {
                throw new \InvalidArgumentException('Sorry, The format of your parameter was not valid.');
            }

            // Display a success message
            $alert = array('type' => 'success', 'message' => 'Query accepted!');
        } catch (Exception $e) {
            // Display an error message
            $alert = array('type' => 'error', 'message' => $e->getMessage());
        }
    }

    return $app['twig']->render('hashtag.twig', array(
        'title' => 'Twitter Analytics',
        'alert' => $alert,
    ));
});

// Handle the user request
$app->match('/user', function (Request $request) use ($app) {
    $alert = null;
    // If the form was submitted, process the input

    /*TODO
    if ('GET' == $request->getMethod()) {
        try {
            // Make sure the photo was uploaded without error
            $criteria = $request->request->get('criteria');
            if ($criteria < 64) {
                // Access ML API for analysis data
                $analysisIn = json_decode(file_get_contents('http://twingiems-env-1.9tmf8mcyqx.us-east-2.elasticbeanstalk.com/?user={$criteria}'),true);
                //$analysisOut = array(
                  //"mct" => $analysisIn["most_controversial_tweet"],
                  //"mpt" => $analysisIn["most_popular_tweet"],
                  //"rel" => $analysisIn["related_hashtag"]
                //);

                // Insert analysis data into DB
                foreach ($analysisOut as $value){
                  $sql = "INSERT INTO {$app['db.userTable']} (analysisText, user) VALUES (:analysisText, :user)";
                  $query = $app['db']->prepare($sql);
                  $data = array(
                      ':analysisText' => $value,
                      ':user'  => $criteria,
                  );
                }
                if (!$query->execute($data)) {
                    throw new \RuntimeException('Transfer of analysis to the database failed.');
                }
            } else {
                throw new \InvalidArgumentException('Sorry, The format of your parameter was not valid.');
            }

            // Display a success message
            $alert = array('type' => 'success', 'message' => 'Query accepted!');
        } catch (Exception $e) {
            // Display an error message
            $alert = array('type' => 'error', 'message' => $e->getMessage());
        }
    }
    */

    return $app['twig']->render('user.twig', array(
        'title' => 'Twitter Analytics',
        'alert' => $alert,
    ));
});

$app->run();
