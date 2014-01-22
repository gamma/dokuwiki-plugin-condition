<?php
	/*
	 * The condition_plugin_custom_tester class defined in <tpl>/condition_plugin_custom_tester.php
	 * MUST implements this class
	 * 
	 * To add a custom test in condition_plugin_custom_tester you just have to add a method like :
	 * 
	 *	function test_dummy($b, &$bug, $lop=false) { if($lop) return array(); return true; }
	 *		this test will react to <if dummy></if> of <if dummy=3></if>
	 * 
	 * or
	 * 
	 *	function test_IP($b, &$bug, $lop=false) {
	 *		if($lop) return array('\=\=?', 'eq', '\!\=', 'neq?', '\~\='); // pcre regexp list of allowed test operators
	 *		$ip = clientIP(true);
	 *		if(!$b['test'] || ($b['test'] == '') || !$b['value'] || ($b['value'] == '') || ($ip == '0.0.0.0')) {
	 *			$bug = true;
	 *			return false;
	 *		}
	 *		switch($b['test']) {
	 *			case '=' :
	 *			case 'eq' :
	 *			case '==' :
	 *				return ($ip == $b['value']); break;
	 *			case '!=' :
	 *			case 'ne' :
	 *			case 'neq' :
	 *				return ($ip != $b['value']); break;
	 *			case '~=' : // such new test operators must be added in syntax.php
	 *				return (strpos($ip, $b['value']) !== false); break;
	 *			default: // non allowed operators for the test must lead to a bug flag raise
	 *				$bug = true;
	 *				return false;
	 *		}
	 *	}
	 *		this test will react to <if IP=127.0.0.1></if>
	 */
	
	class condition_plugin_base_tester {
		function __construct() {}
		
		// Wrapper for all tests
		function run($b, &$bug) {
			if(method_exists($this, 'test_'.$b['key'])) {
				return call_user_func(array($this, 'test_'.$b['key']), $b, $bug);
			}
			$bug = true;
			return false;
		}
		
		// Get allowed keys
		function getkeys() {
			$keys = array();
			foreach(get_class_methods($this) as $m) {
				if(preg_match('`^test_(.+)$`', $m, $r)) $keys[] = $r[1];
			}
			return $keys;
		}
		
		// Get test operators
		function getops() {
			$ops = array();
			foreach($this->getkeys() as $m) $ops = array_merge($ops, call_user_func(array($this, 'test_'.$m), null, $dummy, true));
			return array_unique($ops);
		}
		
		// Tests follows
		// -------------
		
		// user based tests
		function test_user($b, &$bug, $lop=false) {
			if($lop) return array('\=\=?', 'eq', '\!\=', 'neq?');
			$rh = isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : '';
			if(!$b['test'] || ($b['test'] == '')) return ($rh && ($rh != ''));
			switch($b['test']) {
				case '=' :
				case 'eq' :
				case '==' :
					return $rh == $b['value']; break;
				case '!=' :
				case 'ne' :
				case 'neq' :
					return $rh != $b['value']; break;
				default:
					$bug = true;
					return false;
			}
		}
		
		function test_group($b, &$bug, $lop=false) {
			if($lop) return array('\=\=?', 'eq', '\!\=', 'neq?');
			global $INFO;
			$grps = isset($INFO['userinfo']) ? $INFO['userinfo']['grps'] : array();
			if(!$b['test'] || ($b['test'] == '')) return (count($grps) != 0);
			switch($b['test']) {
				case '=' :
				case 'eq' :
				case '==' :
					if(!$b['value'] || ($b['value'] == '')) return (count($grps) == 0);
					return in_array($b['value'], $grps); break;
				case '!=' :
				case 'ne' :
				case 'neq' :
					if(!$b['value'] || ($b['value'] == '')) return (count($grps) != 0);
					return !in_array($b['value'], $grps); break;
				default:
					$bug = true;
					return false;
			}
		}
		
		// namespace based tests
		function test_nsread($b, &$bug, $lop=false) {
			if($lop) return array('\=\=?', 'eq', '\!\=', 'neq?');
			if(!$b['test'] || ($b['test'] == '')) {
				$bug = true;
				return false;
			}
			if(!$b['value'] || ($b['value'] == '')) $b['value'] = '.';
			switch($b['test']) {
				case '=' :
				case 'eq' :
				case '==' :
					return (auth_quickaclcheck($b['value']) >= AUTH_READ); break;
				case '!=' :
				case 'ne' :
				case 'neq' :
					return (auth_quickaclcheck($b['value']) < AUTH_READ); break;
				default:
					$bug = true;
					return false;
			}
		}
		
		function test_nsedit($b, &$bug, $lop=false) {
			if($lop) return array('\=\=?', 'eq', '\!\=', 'neq?');
			if(!$b['test'] || ($b['test'] == '')) {
				$bug = true;
				return false;
			}
			if(!$b['value'] || ($b['value'] == '')) $b['value'] = '.';
			switch($b['test']) {
				case '=' :
				case 'eq' :
				case '==' :
					return (auth_quickaclcheck($b['value']) >= AUTH_EDIT); break;
				case '!=' :
				case 'ne' :
				case 'neq' :
					return (auth_quickaclcheck($b['value']) < AUTH_EDIT); break;
				default:
					$bug = true;
					return false;
			}
		}
		
		// time based tests
		function test_time($b, &$bug, $lop=false) {
			if($lop) return array('\=\=?', 'eq', '\!\=', 'neq?', '\<\=?', 'lt', '\>\=?', 'gt');
			global $INFO;
			if(!$b['test'] || ($b['test'] == '') || !$b['value'] || ($b['value'] == '')) {
				$bug = true;
				return false;
			}
			switch($b['test']) {
				case '=' :
				case 'eq' :
				case '==' :
					return $this->_bt_cmptimeandstr($b['value']); break;
				case '!=' :
				case 'ne' :
				case 'neq' :
					return !$this->_bt_cmptimeandstr($b['value']); break;
				case '<' :
				case 'lt' :
					$t = time();
					return ($t < $this->_bt_strtotime($b['value'], $t)); break;
				case '>' :
				case 'gt' :
					$t = time();
					return ($t > $this->_bt_strtotime($b['value'], $t)); break;
				case '<=' :
					$t = time();
					return ($t <= $this->_bt_strtotime($b['value'], $t)); break;
				case '>=' :
					$t = time();
					return ($t >= $this->_bt_strtotime($b['value'], $t)); break;
				default:
					$bug = true;
					return false;
			}
		}
		function _bt_strtotime($value, $default) {
			if(preg_match('`^([0-9]{4})(-|/)([0-9]{2})(-|/)([0-9]{2})(\s+([0-9]{2}):([0-9]{2})(:([0-9]{2}))?)?$`', $value, $reg)) $value = mktime($reg[7], $reg[8], $reg[10], $reg[3], $reg[5], $reg[1]); // YYYY(-|/)MM(-|/)DD (HH:II(:SS)?)?
			if(preg_match('`^(([0-9]{2}):([0-9]{2})(:([0-9]{2}))?\s+)?([0-9]{4})(-|/)([0-9]{2})(-|/)([0-9]{2})$`', $value, $reg)) $value = mktime($reg[2], $reg[3], $reg[5], $reg[8], $reg[10], $reg[6]); // (HH:II(:SS)?)? YYYY(-|/)MM(-|/)DD
			if(preg_match('`^([0-9]{2})(-|/)([0-9]{2})(-|/)([0-9]{4})(\s+([0-9]{2}):([0-9]{2})(:([0-9]{2}))?)?$`', $value, $reg)) $value = mktime($reg[7], $reg[8], $reg[10], $reg[3], $reg[1], $reg[5]); // DD(-|/)MM(-|/)YYYY (HH:II(:SS)?)?
			if(preg_match('`^(([0-9]{2}):([0-9]{2})(:([0-9]{2}))?\s+)?([0-9]{2})(-|/)([0-9]{2})(-|/)([0-9]{4})$`', $value, $reg)) $value = mktime($reg[2], $reg[3], $reg[5], $reg[8], $reg[6], $reg[10]); // (HH:II(:SS)?)? DD(-|/)MM(-|/)YYYY
			if(!is_numeric($value)) $value = $default;
			return $value;
		}
		function _bt_cmptimeandstr($str) {
			$matched = false;
			$t = time();
			$time = array('y' => date('Y', $t), 'm' => date('m', $t), 'd' => date('d', $t), 'h' => date('H', $t), 'i' => date('i', $t), 's' => date('s', $t));
			$d = array('y' => '', 'm' => '', 'd' => '', 'h' => '', 'i' => '', 's' => '');
			
			// full date y, m and d, time is optionnal
			if(preg_match('`^([0-9]{4})(-|/)([0-9]{2})(-|/)([0-9]{2})(\s+([0-9]{2}):([0-9]{2})(:([0-9]{2}))?)?$`', $str, $reg)) {
				$d = array('y' => $reg[1], 'm' => $reg[3], 'd' => $reg[5], 'h' => $reg[7], 'i' => $reg[8], 's' => $reg[10]); // YYYY(-|/)MM(-|/)DD (HH:II(:SS)?)?
				$matched = true;
			}
			if(preg_match('`^(([0-9]{2}):([0-9]{2})(:([0-9]{2}))?\s+)?([0-9]{4})(-|/)([0-9]{2})(-|/)([0-9]{2})$`', $str, $reg)) {
				$d = array('y' => $reg[6], 'm' => $reg[8], 'd' => $reg[10], 'h' => $reg[2], 'i' => $reg[3], 's' => $reg[5]); // (HH:II(:SS)?)? YYYY(-|/)MM(-|/)DD
				$matched = true;
			}
			if(preg_match('`^([0-9]{2})(-|/)([0-9]{2})(-|/)([0-9]{4})(\s+([0-9]{2}):([0-9]{2})(:([0-9]{2}))?)?$`', $str, $reg)) {
				$d = array('y' => $reg[5], 'm' => $reg[3], 'd' => $reg[1], 'h' => $reg[7], 'i' => $reg[8], 's' => $reg[10]); // DD(-|/)MM(-|/)YYYY (HH:II(:SS)?)?
				$matched = true;
			}
			if(preg_match('`^(([0-9]{2}):([0-9]{2})(:([0-9]{2}))?\s+)?([0-9]{2})(-|/)([0-9]{2})(-|/)([0-9]{4})$`', $str, $reg)) {
				$d = array('y' => $reg[10], 'm' => $reg[8], 'd' => $reg[6], 'h' => $reg[2], 'i' => $reg[3], 's' => $reg[5]); // (HH:II(:SS)?)? DD(-|/)MM(-|/)YYYY
				$matched = true;
			}
			
			// only month and year
			if(preg_match('`^([0-9]{2})(-|/)([0-9]{4})$`', $str, $reg)) {
				$d = array('y' => $reg[3], 'm' => $reg[1], 'd' => '', 'h' => '', 'i' => '', 's' => '');
				$matched = true;
			}
			if(preg_match('`^([0-9]{4})(-|/)([0-9]{2})$`', $str, $reg)) {
				$d = array('y' => $reg[1], 'm' => $reg[3], 'd' => '', 'h' => '', 'i' => '', 's' => '');
				$matched = true;
			}
			
			// only year
			if(preg_match('`^([0-9]{4})$`', $str, $reg)) {
				$d = array('y' => $reg[1], 'm' => '', 'd' => '', 'h' => '', 'i' => '', 's' => '');
				$matched = true;
			}
			
			// full time hours, minutes (opt) and seconds (opt)
			// 11 : 11h
			// 11:30 : 11h30min
			// 11:30:27 : 11h30min27sec
			if(preg_match('`^([0-9]{2})(:([0-9]{2})(:([0-9]{2}))?)?$`', $str, $reg)) {
				$d = array('y' => '', 'm' => '', 'd' => '', 'h' => $reg[7], 'i' => $reg[8], 's' => $reg[10]); // YYYY(-|/)MM(-|/)DD (HH:II(:SS)?)?
				$matched = true;
			}
			
			// custom datetime format : (XX(XX)?i\s?)+
			if(preg_match('`^[0-9]{2}([0-9]{2})?\s?[ymdhis](\s?[0-9]{2}([0-9]{2})?\s?[ymdhis])*$`', $str, $reg)) {
				while(preg_match('`^(([0-9]{2}([0-9]{2})?)\s?([ymdhis]))`', $str, $reg)) {
					$v = $reg[2];
					$i = $reg[4];
					$str = substr($str, strlen($reg[1]));
					if(($i != 'y') || (strlen($v) == 4)) $d[$i] = $v;
				}
				$matched = true;
			}
			
			if(!$matched) return false;
			$same = true;
			foreach($time as $k => $v) if(($d[$k] != '') && ($d[$k] != $v)) $same = false;
			return $same;
		}
		
		// test IP
		function test_IP($b, &$bug, $lop=false) {
			if($lop) return array('\=\=?', 'eq', '\!\=', 'neq?', '\~\=');
			$ip = clientIP(true);
			if(!$b['test'] || ($b['test'] == '') || !$b['value'] || ($b['value'] == '') || ($ip == '0.0.0.0')) {
				$bug = true;
				return false;
			}
			switch($b['test']) {
				case '=' :
				case 'eq' :
				case '==' :
					return ($ip == $b['value']); break;
				case '!=' :
				case 'ne' :
				case 'neq' :
					return ($ip != $b['value']); break;
				case '~=' :
					return (strpos($ip, $b['value']) !== false); break;
				default:
					$bug = true;
					return false;
			}
		}
	}
?>
