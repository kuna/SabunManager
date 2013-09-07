<?

if ($_FILES['file']['error'] > 0) {
	print "#ERROR,File Upload Failed. is the file too big for upload?";
}

if ($_FILES['file']['size'] <= 0) {
	// no file uploaded
	print "#ERROR,No file uploaded";
} else if ($_FILES['file']['size'] > 1000000) {
	// too big file
	print "#ERROR,File size is too big";
} else {
	include("parsebms.php");
	include("mysql.php");
	
	print $_FILES['file']['type'] . "<br>";
	
	// get hash value of the file
	$name = $_FILES['file']['name'];
	$bp = new BMSParser;
	$bp->LoadBMSFile($_FILES['file']['tmp_name']);
	
	// set file name
	print "Hash(Recv):{$bp->bmshash}<br>";
	if (!move_uploaded_file($_FILES['file']['tmp_name'], "./bmsfile/{$bp->bmshash}.bms")) {
		die("Failed to upload BMS.");
	}
	
	// connect to db
	
	// add query
	$query = "SELECT * FROM `BMSInfo` WHERE hash='{$bp->bmshash}'";
	$q = mysql_query($query);
	if (mysql_num_rows($q) <= 0) {
		$query = "INSERT INTO `BMSFile`.`BMSInfo` 
		(`filename`, `hash`, `grouphash`, `encode`, `title`, `artist`, `genre`, `level`, `rank`, `bpm`, `player`, `ref_download`, `ref_link`, `ref_image`, `ref_movie`, `ref_tags`, `ref_detail`, `ref_diff`, `cnt_download`, `date`) VALUES 
		('$name', '{$bp->bmshash}', '{$bp->grouphash}', '{$bp->encode}', '123', '12312', '123', '123', '123', '12', '', '', '', '', '', '', '', '123', CURRENT_TIMESTAMP);";
		$q = mysql_query($query);
		print("#OK,BMS Successfully uploaded.");
	} else {
		die("#ERROR,failed to execute query.");
	}
}

?>