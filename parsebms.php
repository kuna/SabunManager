<?

function LoadBMSFile($path) {
	$fp = fopen($path, "r");
	$size = filesize($path);
	$data = fread($fp, $size);
	$bmshash = hash("md5", $data);
	print "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />";
	
	// check encoding
	$data_encode = iconv("SHIFT_JIS", "UTF-8", $data);
	$encoding = 1;	// SHIFT_JIS
	$data_len = mb_strlen($data_encode);
	for ($i=0; $i<($data_len<1000?$data_len:1000); $i++) {
		$chr = mb_substr($data_encode, $i, 1);
		
		// if val of ord is bigger then 128
		// then it is multibyte
		if (ord($chr)>128) {
			$chr2 = mb_substr($data_encode, $i+1, 1);
			$chr3 = mb_substr($data_encode, $i+2, 1);
			$uchr =  $chr . $chr2 . $chr3;
			$i+=2;
			
			$val = utf8_ord($uchr);	// 44032 ~ 55203: KOR BYTE
			if ($val > 44032 && $val < 55203) {
				$encoding = 2;	// EUC_KR
				break;
			}
		}
	}
	
	if ($encoding == 2) {
		$data_encode = iconv("CP949", "UTF-8", $data);
	}
	
	$lines = split("\r\n", $data_encode);
	foreach ($lines as $line) {
		ProcessBMSLine($line);
	}
}

$parsemode = 0;
function ProcessBMSLine($line) {
	
}

// from http://jmnote.com/wiki/PHP_utf8_chr,_utf8_ord
function utf8_ord($ch) {
	$len = strlen($ch);
	if($len <= 0) return false;
	$h = ord($ch{0});
	if ($h <= 0x7F) return $h;
	if ($h < 0xC2) return false;
	if ($h <= 0xDF && $len>1) return ($h & 0x1F) <<  6 | (ord($ch{1}) & 0x3F);
	if ($h <= 0xEF && $len>2) return ($h & 0x0F) << 12 | (ord($ch{1}) & 0x3F) << 6 | (ord($ch{2}) & 0x3F);          
	if ($h <= 0xF4 && $len>3) return ($h & 0x0F) << 18 | (ord($ch{1}) & 0x3F) << 12 | (ord($ch{2}) & 0x3F) << 6 | (ord($ch{3}) & 0x3F);
	return false;
}
function utf8_chr($num) {
	if($num<128) return chr($num);
	if($num<2048) return chr(($num>>6)+192).chr(($num&63)+128);
	if($num<65536) return chr(($num>>12)+224).chr((($num>>6)&63)+128).chr(($num&63)+128);
	if($num<2097152) return chr(($num>>18)+240).chr((($num>>12)&63)+128).chr((($num>>6)&63)+128).chr(($num&63)+128);
	return false;
}
?>