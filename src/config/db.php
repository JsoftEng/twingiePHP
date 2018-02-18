<?PHP
  class db{
    //properties
    private $dbhost = 'localhost';
    private $dbuser = 'root';
    private $dbpass = '';
    private $dbname = 'twingieDB';

    //connect to db
    public function connect(){
      $mysql_connect_str = "mysql:host=$this->dbhost;dbname=$this->dbname";
      $dbConnect = new PDO($mysql_connect_str, $this->dbuser, $this->dbpass);
      $dbConnect->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

      return $dbConnect;
    }
  }
