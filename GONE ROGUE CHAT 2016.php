<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>gone rogue chat no registration no download required and no trolls allowed free chat.</title>

<!-- Loading the jScrollPane CSS, along with the styling of the
     chat in chat.css and the rest of the page in page.css -->
}
<link rel="stylesheet" type="text/css" href="js/jScrollPane/jScrollPane.css" />
<link rel="stylesheet" type="text/css" href="css/page.css" />
<link rel="stylesheet" type="text/css" href="css/chat.css" />
bootstrap-3.3.6-dist/css/bootstrap-theme.min.css

</head>

<body>

<div id="chatContainer">

    <div id="chatTopBar" class="rounded"></div>
    <div id="chatLineHolder"></div>

    <div id="chatUsers" class="rounded"></div>
    <div id="chatBottomBar" class="rounded">
        <div class="tip"></div>

        <form id="loginForm" method="post" action="">
            <input id="name" name="name" class="rounded" maxlength="20" />
            <input id="email" name="email" class="rounded" />
            <input type="submit" class="blueButton" value="Login" />
        </form>

        <form id="submitForm" method="post" action="">
            <input id="chatText" name="chatText" class="rounded" maxlength="255" />
            <input type="submit" class="blueButton" value="Submit" />
        </form>
		ajax.php

require "classes/DB.class.php";
require "classes/Chat.class.php";
require "classes/ChatBase.class.php";
require "classes/ChatLine.class.php";
require "classes/ChatUser.class.php";

session_name('webchat');
session_start();

if(get_magic_quotes_gpc()){

    // If magic quotes is enabled, strip the extra slashes
    array_walk_recursive($_GET,create_function('&$v,$k','$v = stripslashes($v);'));
    array_walk_recursive($_POST,create_function('&$v,$k','$v = stripslashes($v);'));
}

try{

    // Connecting to the database
    DB::init($dbOptions);

    $response = array();

    // Handling the supported actions:

    switch($_GET['action']){

        case 'login':
            $response = Chat::login($_POST['name'],$_POST['email']);
        break;

        case 'checkLogged':
            $response = Chat::checkLogged();
        break;

        case 'logout':
            $response = Chat::logout();
        break;

        case 'submitChat':
            $response = Chat::submitChat($_POST['chatText']);
        break;

        case 'getUsers':
            $response = Chat::getUsers();
        break;

        case 'getChats':
            $response = Chat::getChats($_GET['lastID']);
        break;

        default:
            throw new Exception('Wrong action');
    }

    echo json_encode($response);
}
catch(Exception $e){
    die(json_encode(array('error' => $e->getMessage())));
}
DB.class.php

class DB {
	private static $instance;
	private $MySQLi;

	private function __construct(array $dbOptions){

		$this->MySQLi = @ new mysqli(	$dbOptions['db_host'],
										$dbOptions['db_user'],
										$dbOptions['db_pass'],
										$dbOptions['db_name'] );

		if (mysqli_connect_errno()) {
			throw new Exception('Database error.');
		}

		$this->MySQLi->set_charset("utf8");
	}

	public static function init(array $dbOptions){
		if(self::$instance instanceof self){
			return false;
		}

		self::$instance = new self($dbOptions);
	}

	public static function getMySQLiObject(){
		return self::$instance->MySQLi;
	}

	public static function query($q){
		return self::$instance->MySQLi->query($q);
	}

	public static function esc($str){
		return self::$instance->MySQLi->real_escape_string(htmlspecialchars($str));
	}
}
ChatBase.class.php

/* This is the base class, used by both ChatLine and ChatUser */

class ChatBase{

    // This constructor is used by all the chat classes:

    public function __construct(array $options){

        foreach($options as $k=>$v){
            if(isset($this->$k)){
                $this->$k = $v;
            }
        }
    }
	ChatLine.class.php

/* Chat line is used for the chat entries */

class ChatLine extends ChatBase{

    protected $text = '', $author = '', $gravatar = '';

    public function save(){
        DB::query("
            INSERT INTO webchat_lines (author, gravatar, text)
            VALUES (
                '".DB::esc($this->author)."',
                '".DB::esc($this->gravatar)."',
                '".DB::esc($this->text)."'
        )");

        // Returns the MySQLi object of the DB class

        return DB::getMySQLiObject();
    }
}
ChatUser.class.php

class ChatUser extends ChatBase{

    protected $name = '', $gravatar = '';

    public function save(){

        DB::query("
            INSERT INTO webchat_users (name, gravatar)
            VALUES (
                '".DB::esc($this->name)."',
                '".DB::esc($this->gravatar)."'
        )");

        return DB::getMySQLiObject();
    }

    public function update(){
        DB::query("
            INSERT INTO webchat_users (name, gravatar)
            VALUES (
                '".DB::esc($this->name)."',
                '".DB::esc($this->gravatar)."'
            ) ON DUPLICATE KEY UPDATE last_activity = NOW()");
    }
}

Chat.class.php – Part 1

/* The Chat class exploses public static methods, used by ajax.php */

class Chat{

    public static function login($name,$email){
        if(!$name || !$email){
            throw new Exception('Fill in all the required fields.');
        }

        if(!filter_input(INPUT_POST,'email',FILTER_VALIDATE_EMAIL)){
            throw new Exception('Your email is invalid.');
        }

        // Preparing the gravatar hash:
        $gravatar = md5(strtolower(trim($email)));

        $user = new ChatUser(array(
            'name'        => $name,
            'gravatar'    => $gravatar
        ));

        // The save method returns a MySQLi object
        if($user->save()->affected_rows != 1){
            throw new Exception('This nick is in use.');
        }

        $_SESSION['user']    = array(
            'name'        => $name,
            'gravatar'    => $gravatar
        );

        return array(
            'status'    => 1,
            'name'        => $name,
            'gravatar'    => Chat::gravatarFromHash($gravatar)
        );
    }

    public static function checkLogged(){
        $response = array('logged' => false);

        if($_SESSION['user']['name']){
            $response['logged'] = true;
            $response['loggedAs'] = array(
                'name'        => $_SESSION['user']['name'],
                'gravatar'    => Chat::gravatarFromHash($_SESSION['user']['gravatar'])
            );
        }

        return $response;
    }

    public static function logout(){
        DB::query("DELETE FROM webchat_users WHERE name = '".DB::esc($_SESSION['user']['name'])."'");

        $_SESSION = array();
        unset($_SESSION);

        return array('status' => 1);
    }
	Chat.class.php – Part 2

    public static function submitChat($chatText){
        if(!$_SESSION['user']){
            throw new Exception('You are not logged in');
        }

        if(!$chatText){
            throw new Exception('You haven\' entered a chat message.');
        }

        $chat = new ChatLine(array(
            'author'    => $_SESSION['user']['name'],
            'gravatar'    => $_SESSION['user']['gravatar'],
            'text'        => $chatText
        ));

        // The save method returns a MySQLi object
        $insertID = $chat->save()->insert_id;

        return array(
            'status'    => 1,
            'insertID'    => $insertID
        );
    }

    public static function getUsers(){
        if($_SESSION['user']['name']){
            $user = new ChatUser(array('name' => $_SESSION['user']['name']));
            $user->update();
        }

        // Deleting chats older than 5 minutes and users inactive for 30 seconds

        DB::query("DELETE FROM webchat_lines WHERE ts < SUBTIME(NOW(),'0:5:0')");
        DB::query("DELETE FROM webchat_users WHERE last_activity < SUBTIME(NOW(),'0:0:30')");

        $result = DB::query('SELECT * FROM webchat_users ORDER BY name ASC LIMIT 18');

        $users = array();
        while($user = $result->fetch_object()){
            $user->gravatar = Chat::gravatarFromHash($user->gravatar,30);
            $users[] = $user;
        }

        return array(
            'users' => $users,
            'total' => DB::query('SELECT COUNT(*) as cnt FROM webchat_users')->fetch_object()->cnt
        );
    }

    public static function getChats($lastID){
        $lastID = (int)$lastID;

        $result = DB::query('SELECT * FROM webchat_lines WHERE id > '.$lastID.' ORDER BY id ASC');

        $chats = array();
        while($chat = $result->fetch_object()){

            // Returning the GMT (UTC) time of the chat creation:

            $chat->time = array(
                'hours'        => gmdate('H',strtotime($chat->ts)),
                'minutes'    => gmdate('i',strtotime($chat->ts))
            );

            $chat->gravatar = Chat::gravatarFromHash($chat->gravatar);

            $chats[] = $chat;
        }

        return array('chats' => $chats);
    }

    public static function gravatarFromHash($hash, $size=23){
        return 'http://www.gravatar.com/avatar/'.$hash.'?size='.$size.'&default='.
                urlencode('http://www.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?size='.$size);
    }
}

    </div>

</div>

<!-- Loading jQuery, the mousewheel plugin and jScrollPane, along with our script.js -->

<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
<script src="js/jScrollPane/jquery.mousewheel.js"></script>
<script src="js/jScrollPane/jScrollPane.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>
<link rel="stylesheet" href="/path/to/hack.css" />
<link rel="stylesheet" href="/path/to/dark.css" />
<body class="hack dark">
<!-- standard mode -->
<link rel="stylesheet" href="/path/to/standard.css">

<body class="standard"></body>
<!-- dark mode -->
<link rel="stylesheet" href="/path/to/dark.css">
<body class="hack dark"></body>
?>
<form class="form">
  <fieldset class="form-group">
    <label for="username">USERNAME:</label>
    <input id="username" type="text" placeholder="ritchie_rich..." class="form-control">
  </fieldset>
  <fieldset class="form-group">
    <label for="email">EMAIL:</label>
    <input id="email" type="email" placeholder="" class="form-control">
  </fieldset>
  <fieldset class="form-group">
    <label for="country">COUNTRY:</label>
    <select id="country" class="form-control">
      <option>China</option>
      <option>U.S.</option>
      <option>U.K.</option>
      <option>Japan</option>
    </select>
  </fieldset>
  <fieldset class="form-group form-textarea">
    <label for="message">MESSAGE:</label>
    <textarea id="message" rows="5" class="form-control"></textarea>
  </fieldset>
  <div class="form-actions">
    <button type="button" class="btn btn-primary btn-block">Submit</button>
  </div>
</form>
<?
***Stateful Form***
.form-group.form-success
.form-group.form-error
.form-group.form-warning

<fieldset class="form-group form-success">
  <label for="phone">Phone Number:</label>
  <input id="phone" type="text" placeholder="" class="form-control">
  <div class="help-block">In this format: +86 xxx xxx xxxxx</div>
</fieldset>
}
<!-- with only an arrow -->
<div class="progress-bar">
  <div class="progress-bar-filled" style="width: 40%"></div>
</div>

<!-- with a percentage showing above the arrow -->
<div class="progress-bar progress-bar-show-percent" data-filled="Loading 40%">
  <div class="progress-bar-filled" style="width: 40%"></div>
</div>

<button class="btn btn-default">Default</button>
<button class="btn btn-primary">Primary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-info">Info</button>
<button class="btn btn-warning">Warning</button>
<button class="btn btn-error">Error</button>

<button class="btn btn-primary btn-ghost">Ghost Button</button>

<div class="btn-group">
  <button class="btn btn-success btn-ghost">Left</button>
  <button class="btn btn-success btn-ghost">Middle</button>
  <button class="btn btn-success btn-ghost">Right</button>
</div>

<div class="card">
  <header class="card-header">title</header>
  <div class="card-content">
    <div class="inner">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Expedita, quas ex vero enim in doloribus officiis ullam vel nam esse sapiente velit incidunt. Eaque quod et, aut maiores excepturi sint.</div>
  </div>
</div>

<div class="alert alert-success">Success message</div>
<div class="alert alert-info">Info message</div>
<div class="alert alert-warning">Warning message</div>
<div class="alert alert-error">Error message</div>

<div class="menu">
   <a class="menu-item">
     item #1 <div class="pull-right">»</div>
   </a>
   <a class="menu-item active">
     item #2 <div class="pull-right">»</div>
   </a>
   <a class="menu-item">
     item #3 <div class="pull-right">»</div>
   </a>
 </div>

<div class="media">
  <div class="media-left">
    <div class="avatarholder">e</div>
  </div>
  <div class="media-body">
    <div class="media-heading">EGOIST @egoist</div>
    <div class="media-content">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Fuga vel, voluptates, doloremque nesciunt illum est corrupti nostrum expedita adipisci dicta vitae? Eveniet maxime quibusdam modi molestias alias et incidunt est.</div>
  </div>
</div>

<!-- right align -->
<div class="media">
  <div class="media-body">
    <div class="media-heading">EGOIST @egoist</div>
    <div class="media-content">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Fuga vel, voluptates, doloremque nesciunt illum est corrupti nostrum expedita adipisci dicta vitae? Eveniet maxime quibusdam modi molestias alias et incidunt est.</div>
  </div>
  <div class="media-right">
    <div class="avatarholder">e</div>
  </div>
</div>

<div class="loading"></div>

<button class="btn btn-info btn-ghost">
  <div class="loading"></div>
  Loading...
</button>
require "classes/DB.class.php";
require "classes/Chat.class.php";
require "classes/ChatBase.class.php";
require "classes/ChatLine.class.php";
require "classes/ChatUser.class.php";

session_name('webchat');
session_start();

if(get_magic_quotes_gpc()){

    // If magic quotes is enabled, strip the extra slashes
    array_walk_recursive($_GET,create_function('&$v,$k','$v = stripslashes($v);'));
    array_walk_recursive($_POST,create_function('&$v,$k','$v = stripslashes($v);'));
}

try{

    // Connecting to the database
    DB::init($dbOptions);

    $response = array();

    // Handling the supported actions:

    switch($_GET['action']){

        case 'login':
            $response = Chat::login($_POST['name'],$_POST['email']);
        break;

        case 'checkLogged':
            $response = Chat::checkLogged();
        break;

        case 'logout':
            $response = Chat::logout();
        break;

        case 'submitChat':
            $response = Chat::submitChat($_POST['chatText']);
        break;

        case 'getUsers':
            $response = Chat::getUsers();
        break;

        case 'getChats':
            $response = Chat::getChats($_GET['lastID']);
        break;

        default:
            throw new Exception('Wrong action');
    }

    echo json_encode($response);
}
catch(Exception $e){
    die(json_encode(array('error' => $e->getMessage())));
}

DB.class.php

class DB {
	private static $instance;
	private $MySQLi;

	private function __construct(array $dbOptions){

		$this->MySQLi = @ new mysqli(	$dbOptions['db_host'],
										$dbOptions['db_user'],
										$dbOptions['db_pass'],
										$dbOptions['db_name'] );

		if (mysqli_connect_errno()) {
			throw new Exception('Database error.');
		}

		$this->MySQLi->set_charset("utf8");
	}

	public static function init(array $dbOptions){
		if(self::$instance instanceof self){
			return false;
		}

		self::$instance = new self($dbOptions);
	}

	public static function getMySQLiObject(){
		return self::$instance->MySQLi;
	}

	public static function query($q){
		return self::$instance->MySQLi->query($q);
	}

	public static function esc($str){
		return self::$instance->MySQLi->real_escape_string(htmlspecialchars($str));
	}
}

ChatBase.class.php

/* This is the base class, used by both ChatLine and ChatUser */

class ChatBase{

    // This constructor is used by all the chat classes:

    public function __construct(array $options){

        foreach($options as $k=>$v){
            if(isset($this->$k)){
                $this->$k = $v;
            }
        }
    }
}

ChatLine.class.php

/* Chat line is used for the chat entries */

class ChatLine extends ChatBase{

    protected $text = '', $author = '', $gravatar = '';

    public function save(){
        DB::query("
            INSERT INTO webchat_lines (author, gravatar, text)
            VALUES (
                '".DB::esc($this->author)."',
                '".DB::esc($this->gravatar)."',
                '".DB::esc($this->text)."'
        )");

        // Returns the MySQLi object of the DB class

        return DB::getMySQLiObject();
    }
}
ChatUser.class.php

class ChatUser extends ChatBase{

    protected $name = '', $gravatar = '';

    public function save(){

        DB::query("
            INSERT INTO webchat_users (name, gravatar)
            VALUES (
                '".DB::esc($this->name)."',
                '".DB::esc($this->gravatar)."'
        )");

        return DB::getMySQLiObject();
    }

    public function update(){
        DB::query("
            INSERT INTO webchat_users (name, gravatar)
            VALUES (
                '".DB::esc($this->name)."',
                '".DB::esc($this->gravatar)."'
            ) ON DUPLICATE KEY UPDATE last_activity = NOW()");
    }
}

Chat.class.php – Part 1

/* The Chat class exploses public static methods, used by ajax.php */

class Chat{

    public static function login($name,$email){
        if(!$name || !$email){
            throw new Exception('Fill in all the required fields.');
        }

        if(!filter_input(INPUT_POST,'email',FILTER_VALIDATE_EMAIL)){
            throw new Exception('Your email is invalid.');
        }

        // Preparing the gravatar hash:
        $gravatar = md5(strtolower(trim($email)));

        $user = new ChatUser(array(
            'name'        => $name,
            'gravatar'    => $gravatar
        ));

        // The save method returns a MySQLi object
        if($user->save()->affected_rows != 1){
            throw new Exception('This nick is in use.');
        }

        $_SESSION['user']    = array(
            'name'        => $name,
            'gravatar'    => $gravatar
        );

        return array(
            'status'    => 1,
            'name'        => $name,
            'gravatar'    => Chat::gravatarFromHash($gravatar)
        );
    }

    public static function checkLogged(){
        $response = array('logged' => false);

        if($_SESSION['user']['name']){
            $response['logged'] = true;
            $response['loggedAs'] = array(
                'name'        => $_SESSION['user']['name'],
                'gravatar'    => Chat::gravatarFromHash($_SESSION['user']['gravatar'])
            );
        }

        return $response;
    }

    public static function logout(){
        DB::query("DELETE FROM webchat_users WHERE name = '".DB::esc($_SESSION['user']['name'])."'");

        $_SESSION = array();
        unset($_SESSION);

        return array('status' => 1);
    }
	Chat.class.php – Part 2

    public static function submitChat($chatText){
        if(!$_SESSION['user']){
            throw new Exception('You are not logged in');
        }

        if(!$chatText){
            throw new Exception('You haven\' entered a chat message.');
        }

        $chat = new ChatLine(array(
            'author'    => $_SESSION['user']['name'],
            'gravatar'    => $_SESSION['user']['gravatar'],
            'text'        => $chatText
        ));

        // The save method returns a MySQLi object
        $insertID = $chat->save()->insert_id;

        return array(
            'status'    => 1,
            'insertID'    => $insertID
        );
    }

    public static function getUsers(){
        if($_SESSION['user']['name']){
            $user = new ChatUser(array('name' => $_SESSION['user']['name']));
            $user->update();
        }

        // Deleting chats older than 5 minutes and users inactive for 30 seconds

        DB::query("DELETE FROM webchat_lines WHERE ts < SUBTIME(NOW(),'0:5:0')");
        DB::query("DELETE FROM webchat_users WHERE last_activity < SUBTIME(NOW(),'0:0:30')");

        $result = DB::query('SELECT * FROM webchat_users ORDER BY name ASC LIMIT 18');

        $users = array();
        while($user = $result->fetch_object()){
            $user->gravatar = Chat::gravatarFromHash($user->gravatar,30);
            $users[] = $user;
        }

        return array(
            'users' => $users,
            'total' => DB::query('SELECT COUNT(*) as cnt FROM webchat_users')->fetch_object()->cnt
        );
    }

    public static function getChats($lastID){
        $lastID = (int)$lastID;

        $result = DB::query('SELECT * FROM webchat_lines WHERE id > '.$lastID.' ORDER BY id ASC');

        $chats = array();
        while($chat = $result->fetch_object()){

            // Returning the GMT (UTC) time of the chat creation:

            $chat->time = array(
                'hours'        => gmdate('H',strtotime($chat->ts)),
                'minutes'    => gmdate('i',strtotime($chat->ts))

            $chat->gravatar = Chat::gravatarFromHash($chat->gravatar);

            $chats[] = $chat;
        }

        return array('chats' => $chats);
    }

    public static function gravatarFromHash($hash, $size=23){
        return 'http://www.gravatar.com/avatar/'.$hash.'?size='.$size.'&default='.
                urlencode('http://www.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?size='.$size);
    }
}
<!-- styles needed by jScrollPane -->
<link type="text/css" href="style/jquery.jscrollpane.css" rel="stylesheet" media="all" />

<!-- latest jQuery direct from google's CDN -->
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js">
</script>

<!-- the mousewheel plugin - optional to provide mousewheel support -->
<script type="text/javascript" src="script/jquery.mousewheel.js"></script>

<!-- the jScrollPane script -->
<script type="text/javascript" src="script/jquery.jscrollpane.min.js"></script>

Then you just need to initialise jScrollPane on document.ready (using a selector which will find the content you want to apply jScrollPane to):

$(function()
{
	$('.scroll-pane').jScrollPane();
});
chat.css – Part 1

/* Main chat container */

#chatContainer{
    width:510px;
    margin:100px auto;
    position:relative;
}

/* Top Bar */

#chatTopBar{
    height:40px;
    background:url('../img/solid_gray.jpg') repeat-x #d0d0d0;
    border:1px solid #fff;
    margin-bottom:15px;
    position:relative;

    color:#777;
    text-shadow:1px 1px 0 #FFFFFF;
}

#chatTopBar .name{
    position:absolute;
    top:10px;
    left:40px;
}

#chatTopBar img{
    left:9px;
    position:absolute;
    top:8px;
}

/* Chats */

#chatLineHolder{
    height:360px;
    width:350px;
    margin-bottom:20px;
    outline:none;
}

.chat{
    background:url('../img/chat_line_bg.jpg') repeat-x #d5d5d5;
    min-height:24px;
    padding:6px;
    border:1px solid #FFFFFF;

    padding:8px 6px 4px 37px;
    position:relative;
    margin:0 10px 10px 0;
}

.chat:last-child{
    margin-bottom:0;
}

.chat .gravatar{
    background:url('http://www.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?size=23') no-repeat;
    left:7px;
    position:absolute;
    top:7px;
}

.chat img{
    display:block;
    visibility:hidden;
}
chat.css – Part 2

/* Chat User Area */

#chatUsers{
    background-color:#202020;
    border:1px solid #111111;
    height:360px;
    position:absolute;
    right:0;
    top:56px;
    width:150px;
}

#chatUsers .user{
    background:url('http://www.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?size=30') no-repeat 1px 1px #444444;
    border:1px solid #111111;
    float:left;
    height:32px;
    margin:10px 0 0 10px;
    width:32px;
}

#chatUsers .user img{
    border:1px solid #444444;
    display:block;
    visibility:hidden;
}

/* Bottom Bar */

#chatBottomBar{
    background:url('../img/solid_gray.jpg') repeat-x #d0d0d0;
    position:relative;
    padding:10px;
    border:1px solid #fff;
}

#chatBottomBar .tip{
    position:absolute;
    width:0;
    height:0;
    border:10px solid transparent;
    border-bottom-color:#eeeeee;
    top:-20px;
    left:20px;
}

#submitForm{
    display:none;
}
chat.css – Part 3

/* Overriding the default styles of jScrollPane */

.jspVerticalBar{
    background:none;
    width:20px;
}

.jspTrack{
    background-color:#202020;
    border:1px solid #111111;
    width:3px;
    right:-10px;
}

.jspDrag {
    background:url('../img/slider.png') no-repeat;
    width:20px;
    left:-9px;
    height:20px !important;
    margin-top:-5px;
}

.jspDrag:hover{
    background-position:left bottom;
}

/* Additional styles */

#chatContainer .blueButton{
    background:url('../img/button_blue.png') no-repeat;
    border:none !important;
    color:#516D7F !important;
    display:inline-block;
    font-size:13px;
    height:29px;
    text-align:center;
    text-shadow:1px 1px 0 rgba(255, 255, 255, 0.4);
    width:75px;
    margin:0;
    cursor:pointer;
}

#chatContainer .blueButton:hover{
    background-position:left bottom;
}
script.js – Part 1

$(document).ready(function(){

    chat.init();

});

var chat = {

    // data holds variables for use in the class:

    data : {
        lastID         : 0,
        noActivity    : 0
    },

    // Init binds event listeners and sets up timers:

    init : function(){

        // Using the defaultText jQuery plugin, included at the bottom:
        $('#name').defaultText('Nickname');
        $('#email').defaultText('Email (Gravatars are Enabled)');

        // Converting the #chatLineHolder div into a jScrollPane,
        // and saving the plugin's API in chat.data:

        chat.data.jspAPI = $('#chatLineHolder').jScrollPane({
            verticalDragMinHeight: 12,
            verticalDragMaxHeight: 12
        }).data('jsp');

        // We use the working variable to prevent
        // multiple form submissions:

        var working = false;

        // Logging a person in the chat:

        $('#loginForm').submit(function(){

            if(working) return false;
            working = true;

            // Using our tzPOST wrapper function
            // (defined in the bottom):

            $.tzPOST('login',$(this).serialize(),function(r){
                working = false;

                if(r.error){
                    chat.displayError(r.error);
                }
                else chat.login(r.name,r.gravatar);
            });

            return false;
        });

 // Submitting a new chat entry:

        $('#submitForm').submit(function(){

            var text = $('#chatText').val();

            if(text.length == 0){
                return false;
            }

            if(working) return false;
            working = true;

            // Assigning a temporary ID to the chat:
            var tempID = 't'+Math.round(Math.random()*1000000),
                params = {
                    id            : tempID,
                    author        : chat.data.name,
                    gravatar    : chat.data.gravatar,
                    text        : text.replace(/</g,'&lt;').replace(/>/g,'&gt;')
                };

            // Using our addChatLine method to add the chat
            // to the screen immediately, without waiting for
            // the AJAX request to complete:

            chat.addChatLine($.extend({},params));

            // Using our tzPOST wrapper method to send the chat
            // via a POST AJAX request:

            $.tzPOST('submitChat',$(this).serialize(),function(r){
                working = false;

                $('#chatText').val('');
                $('div.chat-'+tempID).remove();

                params['id'] = r.insertID;
                chat.addChatLine($.extend({},params));
            });

            return false;
        });

        // Logging the user out:

        $('a.logoutButton').live('click',function(){

            $('#chatTopBar > span').fadeOut(function(){
                $(this).remove();
            });

            $('#submitForm').fadeOut(function(){
                $('#loginForm').fadeIn();
            });

            $.tzPOST('logout');

            return false;
        });

        // Checking whether the user is already logged (browser refresh)

        $.tzGET('checkLogged',function(r){
            if(r.logged){
                chat.login(r.loggedAs.name,r.loggedAs.gravatar);
            }
        });

        // Self executing timeout functions

        (function getChatsTimeoutFunction(){
            chat.getChats(getChatsTimeoutFunction);
        })();

        (function getUsersTimeoutFunction(){
            chat.getUsers(getUsersTimeoutFunction);
        })();

    },

script.js – Part 3

    // The login method hides displays the
    // user's login data and shows the submit form

    login : function(name,gravatar){

        chat.data.name = name;
        chat.data.gravatar = gravatar;
        $('#chatTopBar').html(chat.render('loginTopBar',chat.data));

        $('#loginForm').fadeOut(function(){
            $('#submitForm').fadeIn();
            $('#chatText').focus();
        });

    },

    // The render method generates the HTML markup
    // that is needed by the other methods:

    render : function(template,params){

        var arr = [];
        switch(template){
            case 'loginTopBar':
                arr = [
                '<span><img src="',params.gravatar,'" width="23" height="23" />',
                '<span class="name">',params.name,
                '</span><a href="" class="logoutButton rounded">Logout</a></span>'];
            break;

            case 'chatLine':
                arr = [
                    '<div class="chat chat-',params.id,' rounded"><span class="gravatar">'+
                    '<img src="',params.gravatar,'" width="23" height="23" '+
                    'onload="this.style.visibility=\'visible\'" />',
                    '</span><span class="author">',params.author,
                    ':</span><span class="text">',params.text,
                    '</span><span class="time">',params.time,'</span></div>'];
            break;

            case 'user':
                arr = [
                    '<div class="user" title="',params.name,'"><img src="',params.gravatar,
                    '" width="30" height="30" onload="this.style.visibility=\'visible\'"'+
                    ' /></div>'
                ];
            break;
        }

        // A single array join is faster than
        // multiple concatenations

        return arr.join('');

    },

ript.js – Part 4

// The addChatLine method ads a chat entry to the page

    addChatLine : function(params){

        // All times are displayed in the user's timezone

        var d = new Date();
        if(params.time) {

            // PHP returns the time in UTC (GMT). We use it to feed the date
            // object and later output it in the user's timezone. JavaScript
            // internally converts it for us.

            d.setUTCHours(params.time.hours,params.time.minutes);
        }

        params.time = (d.getHours() < 10 ? '0' : '' ) + d.getHours()+':'+
                      (d.getMinutes() < 10 ? '0':'') + d.getMinutes();

        var markup = chat.render('chatLine',params),
            exists = $('#chatLineHolder .chat-'+params.id);

        if(exists.length){
            exists.remove();
        }

        if(!chat.data.lastID){
            // If this is the first chat, remove the
            // paragraph saying there aren't any:

            $('#chatLineHolder p').remove();
        }

        // If this isn't a temporary chat:
        if(params.id.toString().charAt(0) != 't'){
            var previous = $('#chatLineHolder .chat-'+(+params.id - 1));
            if(previous.length){
                previous.after(markup);
            }
            else chat.data.jspAPI.getContentPane().append(markup);
        }
        else chat.data.jspAPI.getContentPane().append(markup);

        // As we added new content, we need to
        // reinitialise the jScrollPane plugin:

        chat.data.jspAPI.reinitialise();
        chat.data.jspAPI.scrollToBottom(true);

    }script.js – Part 5

// This method requests the latest chats
    // (since lastID), and adds them to the page.

    getChats : function(callback){
        $.tzGET('getChats',{lastID: chat.data.lastID},function(r){

            for(var i=0;i<r.chats.length;i++){
                chat.addChatLine(r.chats[i]);
            }

            if(r.chats.length){
                chat.data.noActivity = 0;
                chat.data.lastID = r.chats[i-1].id;
            }
            else{
                // If no chats were received, increment
                // the noActivity counter.

                chat.data.noActivity++;
            }

            if(!chat.data.lastID){
                chat.data.jspAPI.getContentPane().html('<p class="noChats">No chats yet</p>');
            }

            // Setting a timeout for the next request,
            // depending on the chat activity:

            var nextRequest = 1000;

            // 2 seconds
            if(chat.data.noActivity > 3){
                nextRequest = 2000;
            }

            if(chat.data.noActivity > 10){
                nextRequest = 5000;
            }

            // 15 seconds
            if(chat.data.noActivity > 20){
                nextRequest = 15000;
            }

            setTimeout(callback,nextRequest);
        });
    },

    // Requesting a list with all the users.

    getUsers : function(callback){
        $.tzGET('getUsers',function(r){

            var users = [];

            for(var i=0; i< r.users.length;i++){
                if(r.users[i]){
                    users.push(chat.render('user',r.users[i]));
                }
            }

            var message = '';

            if(r.total<1){
                message = 'No one is online';
            }
            else {
                message = r.total+' '+(r.total == 1 ? 'person':'people')+' online';
            }

            users.push('<p class="count">'+message+'</p>');

            $('#chatUsers').html(users.join(''));

            setTimeout(callback,15000);
        });
    },

script.js – Part 6

    // This method displays an error message on the top of the page:

    displayError : function(msg){
        var elem = $('<div>',{
            id        : 'chatErrorMessage',
            html    : msg
        });

        elem.click(function(){
            $(this).fadeOut(function(){
                $(this).remove();
            });
        });

        setTimeout(function(){
            elem.click();
        },5000);

        elem.hide().appendTo('body').slideDown();
    }
};

// Custom GET & POST wrappers:

$.tzPOST = function(action,data,callback){
    $.post('php/ajax.php?action='+action,data,callback,'json');
}

$.tzGET = function(action,data,callback){
    $.get('php/ajax.php?action='+action,data,callback,'json');
}

// A custom jQuery method for placeholder text:

$.fn.defaultText = function(value){

    var element = this.eq(0);
    element.data('defaultText',value);

    element.focus(function(){
        if(element.val() == value){
            element.val('').removeClass('defaultText');
        }
    }).blur(function(){
        if(element.val() == '' || element.val() == value){
            element.addClass('defaultText').val(value);
        }
    });

    return element.blur();
}