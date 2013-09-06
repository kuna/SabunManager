<?

$mysql_host = "127.0.0.1";
$mysql_id = "root";
$mysql_passwd = "";

function ConnectMySQL() {	
	$mysql = mysql_connect($mysql_host, $mysql_id, $mysql_passwd)
		or die("#ERROR,UnableToConnectMySQL,".mysql_error());
	mysql_select_db("LR2IR") or die("#ERROR,UnableToConnectMySQL");
}

function CloseMySQL() {
	mysql_close($mysql);
}

?>