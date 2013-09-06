<?

if ($_FILES['file']['error'] > 0) {
	print "File Upload Failed. is file too big?";
}

if ($_FILES['file']['size'] <= 0) {
	// no file uploaded
} else if ($_FILES['file']['size'] > 1000000) {
	// too big file
} else {
	include("parsebms.php");
	include("mysql.php");
	
	// get hash value of the file
	LoadBMSFile($_FILES['file']['tmp_name']);
	
	// set file name
	if (move_uploaded_file($_FILES['file']['tmp_name'], "./bmsfile/$bmshash.bms")) {
		print("BMS Successfully uploaded.");
	} else {
		die("Failed to upload BMS.");
	}
	
	// connect to db
	
	// add query
	$query = "INSERT INTO BMSFile SET()";
}

?>