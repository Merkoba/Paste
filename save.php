<?php

ob_start();

function is_null_or_empty_string($str)
{
	return (!isset($str) || trim($str) === '');
}

function random_number_string($length = 10) 
{
	return substr(str_shuffle(str_repeat($x='0123456789', ceil($length/strlen($x)) )),1,$length);
}

function random_word($length = 4)
{  
	$string = '';
	
	$vowels = array("a","e","i","o","u");  
	
	$consonants = array(
	'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 
	'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z'
	);

	// Seed it
	srand((double) microtime() * 1000000);
	$max = $length/2;

	for ($i = 1; $i <= $max; $i++)
	{
		$string .= $consonants[rand(0,19)];
		$string .= $vowels[rand(0,4)];
	}

	return $string;
}

function random_string($length = 10) 
{
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';

	for ($i = 0; $i < $length; $i++) 
	{
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}

	return $randomString;
}

function generate_token($ourl)
{
	return $ourl . random_string(40);
}

$max_content_length = 500000;
$max_comment_length = 200;

// Create a new database, if the file doesn't exist and open it for reading/writing.
// The extension of the file is arbitrary.
$db = new SQLite3('pastes_v4.sqlite', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);

// Create a table.
$db->query('CREATE TABLE IF NOT EXISTS "pastes" (
	"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	"content" VARCHAR,
	"mode_name" VARCHAR,
	"code" VARCHAR,
	"revision" INTEGER,
	"comment" VARCHAR,
	"date" DATETIME,
	"modified" DATETIME,
	"token" VARCHAR
)');

$db->exec('BEGIN');

$date = time();

if(isset($_POST["content"]))
{
	$content = $_POST["content"];
}

else
{
	exit();
}

$content_length = strlen($content);

if($content_length > $max_content_length)
{
	exit();
}

if(isset($_POST["comment"]))
{
	$comment = $_POST["comment"];
}

else
{
	$comment = "";
}

$comment_length = strlen($comment);

if($comment_length > $max_comment_length)
{
	exit();
}

if($content_length === 0 && $comment_length === 0)
{
	exit();
}

if(isset($_POST["mode_name"]))
{
	$mode_name = $_POST["mode_name"];

	if(is_null_or_empty_string($mode_name))
	{
		$mode_name = "Plain Text";
	}
}

else
{
	$mode_name = "Plain Text";
}

if(isset($_POST["token"]))
{
	$token = $_POST["token"];

	if(is_null_or_empty_string($token))
	{
		$update = false;
	}

	else
	{
		$update = true;
	}
}

else
{
	$update = false;
}

if($update)
{	
	$statement = $db->prepare('SELECT code, revision FROM "pastes" WHERE "token" = ?');
	$statement->bindValue(1, $token);
	$result = $statement->execute();
	$array = $result->fetchArray(SQLITE3_ASSOC);

	if($array != false)
	{
		$code = $array["code"];
		$revision = $array["revision"];
		$url = $code . "-" . $revision;
	}

	else
	{
		exit();
	}
}

else
{
	if(isset($_SERVER['HTTP_REFERER']))
	{
		$ourl = $_SERVER['HTTP_REFERER'];

		if(is_null_or_empty_string($ourl))
		{
			$url = "";
		}

		else
		{
			$ourl = strtok($ourl, '?');
			$exploded = explode("/", $ourl);
			$url = array_pop($exploded);
		}
	}

	else
	{
		$url = "";
	}

	if(is_null_or_empty_string($url))
	{
		$revision = 1;
		$code = $date . "-" . random_word(6);
		$url = $code . "-" . $revision;
	}

	else
	{
		$url_split = explode("-", $url);
		$code = $url_split[0] . "-" . $url_split[1];
		$num_revisions = $db->querySingle('SELECT COUNT(DISTINCT "revision") FROM "pastes" WHERE "code" = "' . $code . '" ');	
		$revision = $num_revisions + 1;
		$url = $code . "-" . $revision;
	}

	$token = generate_token($url);
}

if($update)
{
	$statement = $db->prepare('UPDATE "pastes" 
		SET content=:acontent, mode_name=:amode_name, comment=:acomment, modified=:amodified
		WHERE token=:token');

	$statement->bindValue(':acontent', $content);
	$statement->bindValue(':amode_name', $mode_name);
	$statement->bindValue(':acomment', $comment);
	$statement->bindValue(':amodified', $date);
	$statement->bindValue(':token', $token);

	$statement->execute();
}

else
{
	$statement = $db->prepare('INSERT INTO "pastes" 
		("content", "mode_name", "code", "revision", "comment", "date", "modified", "token")
		VALUES (:acontent, :amode_name, :acode, :arevision, :acomment, :adate, :amodified, :atoken)');

	$statement->bindValue(':acontent', $content);
	$statement->bindValue(':amode_name', $mode_name);
	$statement->bindValue(':acode', $code);
	$statement->bindValue(':arevision', $revision);
	$statement->bindValue(':acomment', $comment);
	$statement->bindValue(':adate', $date);
	$statement->bindValue(':amodified', $date);
	$statement->bindValue(':atoken', $token);

	$statement->execute();
}

$db->exec('COMMIT');

$db->close();

$response = array('url' => $url, 'token' => $token);

echo json_encode($response);