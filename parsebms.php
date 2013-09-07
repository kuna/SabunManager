<?

class BMSParser {
	var $size;
	var $data;
	var $data_encode;
	var $encoding;
	var $data_len;
	var $bmshash;
	var $grouphash;
	
	var $header;
	
	// need calculation
	var $notecnt;
	var $lnnotecnt;
	var $time;
	
	// for parsing; private
	var $fp;
	var $lnobj;
	var $length_beat;
	var $parsemode = 0;
	var $bpm;
	var $stop;
	var $note_bpm;
	var $note_ln;
	var $lastbeat;
	
	function LoadBMSFile($path) {
		$this->fp = fopen($path, "r");
		$this->size = filesize($path);
		$this->data = fread($this->fp, $this->size);
		$this->bmshash = hash("md5", $this->data);
		print "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />";
		
		// check encoding
		$this->data_encode = iconv("SHIFT_JIS", "UTF-8", $this->data);
		$this->encoding = 1;	// SHIFT_JIS
		$this->data_len = mb_strlen($this->data_encode);
		$parselen = ($this->data_len<1000?$this->data_len:1000);
		for ($i=0; $i<$parselen; $i++) {
			$chr = mb_substr($this->data_encode, $i, 1);
			
			// if val of ord is bigger then 128
			// then it is multibyte
			if (ord($chr)>128) {
				$chr2 = mb_substr($this->data_encode, $i+1, 1);
				$chr3 = mb_substr($this->data_encode, $i+2, 1);
				$uchr =  $chr . $chr2 . $chr3;
				$i+=2;
				
				$val = utf8_ord($uchr);	// 44032 ~ 55203: KOR BYTE
				if ($val > 44032 && $val < 55203) {
					$this->encoding = 2;	// EUC_KR
					break;
				}
			}
		}
		
		if ($this->encoding == 2) {
			$this->data_encode = iconv("CP949", "UTF-8", $this->data);
		}
		
		// init value
		$this->notecnt = 0;
		$this->time = 0;
		$this->header["lntype"] = 1;
		for ($i=0; $i<1000; $i++) {
			$length_beat[$i] = 1;
		}
		
		// start parsing
		$lines = split("\r\n", $this->data_encode);
		foreach ($lines as $line) {
			$this->ProcessBMSLine($line);
		}
		
		// sort
		usort($this->note_bpm, $this->cmp_beat);
		usort($this->note_ln, $this->cmp_beat);
		
		// proc time & longnote
		$this->ProcessTime();
		$this->ProcessLongnote();
		
		// create grouphash
		$this->grouphash = $this->generateGroupHash();
	}
	
	function ProcessBMSLine($line) {
		// preprocessor start
		// preprocessor end
		
		if ($line == "*---------------------- HEADER FIELD") {
			$this->parsemode = 1;
			return;
		}
		if ($line == "*---------------------- MAIN DATA FIELD") {
			$this->parsemode = 2;
			return;
		}
		if ($line == "*---------------------- BGA FIELD") {
			$this->parsemode = 3;
			return;
		}
		
		if ($this->parsemode == 1 || $this->parsemode == 3) {
			$args = split(" ", $line);
			if (count($args) > 1) {
				if (strcasecmp($args[0], "#TITLE") == 0) {
					$this->header["title"] = $args[1];
				} else if (strcasecmp($args[0], "#SUBTITLE") == 0) {
					$this->header["subtitle"] = $args[1];
				} else if (strcasecmp($args[0], "#PLAYER") == 0) {
					$this->header["player"] = $args[1];
				} else if (strcasecmp($args[0], "#GENRE") == 0) {
					$this->header["genre"] = $args[1];
				} else if (strcasecmp($args[0], "#ARTIST") == 0) {
					$this->header["artist"] = $args[1];
				} else if (strcasecmp($args[0], "#BPM") == 0) {
					$this->header["bpm"] = floatval($args[1]);
				} else if (strcasecmp($args[0], "#DIFFICULTY") == 0) {
					$this->header["difficulty"] = $args[1];
				} else if (strcasecmp($args[0], "#PLAYLEVEL") == 0) {
					$this->header["level"] = $args[1];
				} else if (strcasecmp($args[0], "#RANK") == 0) {
					$this->header["rank"] = $args[1];
				} else if (strcasecmp($args[0], "#TOTAL") == 0) {
					$this->header["total"] = $args[1];
				} else if (strcasecmp($args[0], "#STAGEFILE") == 0) {
					$this->header["stagefile"] = $args[1];
				} else if (strcasecmp($args[0], "#LNTYPE") == 0) {
					$this->header["lntype"] = $args[1];
				} else if (strcasecmp(substr($args[0], 0, 4), "#STP") == 0) {
					//$pt = split("[.]", substr(args[0], 4));
					$this->time += floatval($args[1]);
				} else if (strcasecmp(substr($args[0], 0, 6), "#LNOBJ") == 0) {
					$this->lnobj[$this->Hex36toInt($args[1])] = true;
				} else if (strcasecmp(substr($args[0], 0, 4), "#BMP") == 0) {
					$this->header["bmp"][$this->Hex36toInt($args[1])] = args[1];
				} else if (strcasecmp(substr($args[0], 0, 4), "#WAV") == 0) {
					$this->header["wav"][$this->Hex36toInt($args[1])] = args[1];
				} else if (strcasecmp(substr($args[0], 0, 4), "#BPM") == 0) {
					$this->bpm[$this->Hex36toInt(substr($args[0], 4, 2))] = floatval(args[1]);
				} else if (strcasecmp(substr($args[0], 0, 5), "#STOP") == 0) {
					$this->stop[$this->Hex36toInt(substr($args[0], 4, 2))] = floatval(args[1]);
				}
			}
		}
		
		if ($this->parsemode == 2 || $this->parsemode== 3) {
			$args = split(":", $line);
			if (count($args) > 1) {
				$beat = intval(substr(args[0], 1, 3));
				$channel = intval(substr(args[0], 4, 2));
				
				if ($channel == 2) {
					$this->length_beat[$beat] = floatval(args[1]);
				} else {
					$ncb = strlen(args[1])/2;
					for ($i=0; $i<$ncb; $i++) {
						$val_str = substr($args[1], $i*2,2);
						$val = $this->Hex36toInt($val_str);
						if ($val == 0) continue;
						$nb = $beat + $i/$ncb*2;
						
						// BPM
						if ($channel == 3) {
							$obj = array($nb, intval($val, 16));
							array_push($this->note_bpm, $obj);
						}
						
						// Extended BPM
						if ($channel == 8) {
							$obj = array($nb, $this->bpm[$val]);
							array_push($this->note_bpm, $obj);
						}
						
						// STOP
						if ($channel == 9) {
							$this->time += $this->stop[$val];
						}
						
						// Normal Object
						if (($channel > 10 && $channel < 20) ||
							 ($channel > 30 && $channel < 40)) {
							$this->notecnt++;
							
							// check LNObj Command
							if ($this->lnobj[$val]) {
								$this->notecnt--;
								$this->lnnotecnt ++;
							}
						}
						
						// LNNote
						if ($channel > 50 && $channel < 70) {
							$obj = array($nb, $val, $this->header["lntype"]);
							array_push($this->note_ln, $obj);
						}
					}
					
					// store last beat
					if ($ncb > $this->lastbeat) $this->lastbeat = $ncb;
				}
			}
		}
	}
	
	function ProcessTime() {
		$bpm = $this->header["bpm"];
		$beat = 0;
		
		for ($i=0; $i<count($this->note_bpm); $i++) {
			$d = $this->note_bpm[$i];
			while (d[0] >= (int)$beat+1) {
				$this->time += ((int)$beat+1-$beat) * (1/$bpm*60*4) * $this->length_beat[(int)$beat];
				$beat = (int)$beat+1;
			}
			
			$this->time += (d[0] - $beat) * (1/$bpm*60*4) * $this->length_beat[(int)$beat];
			$bpm = d[1];
		}
	}
	
	function ProcessLongnote() {
		for ($i=0; $i<count($this->note_ln); $i++) {
			$d = $this->note_ln[$i];
			if ($d[2] == 1) {
				// LNTYPE 1
				for ($j = $i+1; $j<count($this->note_ln); $j++) {
					$d2 = $this->note_ln[$j];
					if ($d2[1] == $d[1] && $d2[2] == 1) {
						$d2[2] = -1;	// used
						break;
					}
				}
				$this->notecnt++;
				$this->lnnotecnt++;
			} else if ($d[2] == 2) {
				// LNTYPE 2
				for ($j = $i+1; $j<count($this->note_ln); $j++) {
					$d2 = $this->note_ln[$j];
					if ($d2[1] == $d[1] && $d2[2] == 2) {
						if ($d[1] != $d2[1]) break;
						$d2[2] = -1;	// used
					}
				}
				$this->notecnt++;
				$this->lnnotecnt++;
			}
		}
	}
	
	function cmp_beat($a, $b) {
		return ($a[0]-$b[0]);
	}
	
	function generateGroupHash() {
		// use WAV & BMP data
		// if all of them null? then no hash
		$v = "";
		for ($i=0; $i<1322; $i++) {
			if ($header['wav'][$i] != NULL)
				$v .= $header['wav'][$i];
		}
		for ($i=0; $i<1322; $i++) {
			if ($header['bmp'][$i] != NULL)
				$v .= $header['bmp'][$i];
		}
		return hash("md5", $v);
	}
	
	function Hex36toInt($hex) {
		$sample = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$r = 0;
		for ($i=0; $i<strlen($hex); $i++) {
			$r *= 36;
			for ($j=0; $j<strlen($sample); $j++) {
				if (substr($r, $i, 1) == substr($sample, $j, 1)) {
					$r += $j;
					continue;
				}
			}
		}
		return $r;
	}
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