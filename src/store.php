<?php
/**
 * The following serves as the queuing service for buffering data in transit between
 * twitter analytics service and database.
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

 use PhpAmqpLib\Connection\AMQPStreamConnection;
 use PhpAmqpLib\Message\AMQPMessage;

 function streamConnect(&$connection, &$channel){
   $connection = new AMQPStreamConnection('localhost',5672, 'guest', 'guest');
   $channel = $connection->channel();
 }

 function storeMessage(){
   $connection = null;
   $channel = null;
   $msg = null;

   streamConnect($connection,$channel);

   $channel->queue_declare('hello', false, false, false, false);
   $msg = new AMQPMessage('Hello World');
   $channel->basic_publish($msg,'','hello');

   echo "Message stored!";

   $channel->close();
   $connection->close();
 }

?>
