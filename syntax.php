<?php
/**
 * Condition Plugin: render a block if a condition if fullfilled
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Etienne Meleard <etienne.meleard@free.fr>
 * 
 * 2009/06/08 : Creation
 * 2009/06/09 : Drop of the multi-value tests / creation of tester class system
 * 2009/06/10 : Added tester class override to allow user to define custom tests
 * 2010/06/09 : Changed $tester visibility to ensure compatibility with PHP4
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_condition extends DokuWiki_Syntax_Plugin {
	// To be used by _processblocks to mix the test results together
	var $allowedoperators = array('\&\&', '\|\|', '\^', 'and', 'or', 'xor'); // plus '!' specific operator
	
	// Allowed test operators, their behavior is defined in the tester class, they are just defined here for recognition during parsing
	var $allowedtests = array();
	
	// Allowed test keys
	var $allowedkeys = array();
	
	// To store the tester object
	var $tester = null;
	
	/*function accepts($mode) { return true; }
	function getAllowedTypes() {
		return array('container', 'baseonly', 'formatting', 'substition', 'protected', 'disabled', 'paragraphs'); // quick hack
		}*/
	
	function getType() { return 'container';}
	function getPType() { return 'normal';}
	function getSort() { return 5; } // condition is top priority
	
	// Connect pattern to lexer
	function connectTo($mode){
		$this->Lexer->addEntryPattern('<if(?=.*?</if>)', $mode, 'plugin_condition');
	}

	function postConnect() {
		$this->Lexer->addExitPattern('</if>', 'plugin_condition');
	}

	// Handle the match
	function handle($match, $state, $pos, &$handler) {
		if($state != DOKU_LEXER_UNMATCHED) return false;
		
		// Get allowed test operators
		$this->_loadtester();
		if(!$this->tester) return array(array(), '');
		$this->allowedtests = $this->tester->getops();
		$this->allowedkeys = $this->tester->getkeys();
		
		$blocks = array();
		$content = '';
		$this->_parse($match, $blocks, $content);
		
		return array($blocks, $content);
	}
	
	// extracts condition / content
	function _parse(&$match, &$b, &$ctn) {
		$match = preg_replace('`^\s+`', '', $match); // trim heading whitespaces
		$b = $this->_fetch_block($match, 0);
		if($match != '') $ctn = preg_replace('`\n+$`', '', preg_replace('`^\n+`', '', preg_replace('`^>`', '', $match)));
		return true;
	}
	
	// fetch a condition block from buffer
	function _fetch_block(&$match, $lvl=0) {
		$match = preg_replace('`^\s+`', '', $match); // trim heading whitespaces
		$instrs = array();
		$continue = true;
		
		while(($match{0} != '>') && ($match != '') && (($lvl == 0) || ($match{0} != ')')) && $continue) {
			$i = array('type' => null, 'key' => '', 'test' => '', 'value' => '', 'next' => '');
			if($this->_fetch_op($match, true)) { // ! heading equals block descending for first token
				$i['type'] = 'nblock';
				$match = substr($match, 1); // remove heading !
				$i['value'] = $this->_fetch_block($match, $lvl+1);
			}else if($this->_is_block($match)) {
				$i['type'] = 'block';
				$match = substr($match, 1); // remove heading (
				$i['value'] = $this->_fetch_block($match, $lvl+1);
			}else if($this->_is_key($match, $key)) {
				$i['type'] = 'test';
				$i['key'] = $key;
				$match = substr($match, strlen($key)); // remove heading key
				if($this->_is_test($match, $test)) {
					$i['test'] = $test;
					$match = substr($match, strlen($test)); // remove heading test
					if(($v = $this->_fetch_value($match)) !== null) $i['value'] = $v;
				}
			}else $match = preg_replace('`^[^>\s\(]+`', '', $match); // here dummy stuff remains
			if($i['type']) {
				if(($op = $this->_fetch_op($match, false)) !== null) {
					$match = substr($match, strlen($op)); // remove heading op
					$i['next'] = $op;
				}else $continue = false;
				$instrs[] = $i;
			}
		}
		return $instrs;
	}
	
	// test if buffer starts with new sub-block
	function _is_block(&$match) {
		$match = preg_replace('`^\s+`', '', $match); // trim heading whitespaces
		return preg_match('`^\(`', $match);
	}
	
	// test if buffer starts with a key ref
	function _is_key(&$match, &$key) {
		$match = preg_replace('`^\s+`', '', $match); // trim heading whitespaces
		if(preg_match('`^([a-zA-Z0-9_-]+)`', $match, $r)) {
			if(preg_match('`^'.$this->_preg_build_alternative($this->allowedkeys).'$`', $r[1])) {
				$key = $r[1];
				return true;
			}
		}
		return false;
	}
	
	// build a pcre alternative escaped test from array
	function _preg_build_alternative($choices) {
		//$choices = array_map(create_function('$e', 'return preg_replace(\'`([^a-zA-Z0-9])`\', \'\\\\\\\\$1\', $e);'), $choices);
		return '('.implode('|', $choices).')';
	}
	
	// tells if buffer starts with a test operator
	function _is_test(&$match, &$test) {
		$match = preg_replace('`^\s+`', '', $match); // trim heading whitespaces
		if(preg_match('`^'.$this->_preg_build_alternative($this->allowedtests).'`', $match, $r)) { $test = $r[1]; return true; }
		return false;
	}
	
	// fetch value from buffer, handles value quoting
	function _fetch_value(&$match) {
		$match = preg_replace('`^\s+`', '', $match); // trim heading whitespaces
		if($match{0} == '"') {
			$match = substr($match, 1);
			$value = substr($match, 0, strpos($match, '"'));
			$match = substr($match, strlen($value) + 1);
		}else{
			$psp = strpos($match, ')');
			$wsp = strpos($match, ' ');
			$esp = strpos($match, '>');
			$sp = 0;
			$bug = false;
			if(($wsp === false) && ($esp === false) && ($psp === false)) {
				return null; // BUG
			}else if(($wsp === false) && ($esp === false)) {
				$sp = $psp;
			}else if(($wsp === false) && ($psp === false)) {
				$sp = $esp;
			}else if(($psp === false) && ($esp === false)) {
				$sp = $wsp;
			}else if($wsp === false) {
				$sp = min($esp, $psp);
			}else if($esp === false) {
				$sp = min($wsp, $psp);
			}else if($psp === false) {
				$sp = min($esp, $wsp);
			}else $sp = min($wsp, $esp, $psp);
			
			$value = substr($match, 0, $sp);
			$match = substr($match, strlen($value));
		}
		return $value;
	}
	
	// fetch a logic operator from buffer
	function _fetch_op(&$match, $head=false) {
		$match = preg_replace('`^\s+`', '', $match); // trim heading whitespaces
		$ops = $this->allowedoperators;
		if($head) $ops = array('!');
		if(preg_match('`^'.$this->_preg_build_alternative($ops).'`', $match, $r)) return $r[1];
		return null;
	}
	
	/**
	 * Create output
	 */
	function render($mode, &$renderer, $data) {
		global $INFO;
		if(count($data) != 2) return false;
		if($mode == 'xhtml') {
			global $ID;
			// prevent caching to ensure good user data detection for tests
			$renderer->info['cache'] = false;
			
			$blocks = $data[0];
			$content = $data[1];
			
			// parsing content for a <else> statement
			$else = '';
			if(strpos($content, '<else>') !== false) {
				$i = explode('<else>', $content);
				$content = $i[0];
				$else = implode('', array_slice($i, 1));
			}
			
			// Process condition blocks
			$bug = false;
			$this->_loadtester();
			$ok = $this->_processblocks($blocks, $bug);
			
			// Render content if all went well
			$toc = $renderer->toc;
			if(!$bug) {
			  $instr = p_get_instructions($ok ? $content : $else);
			  foreach($instr as $instruction) {
			  	if ( in_array($instruction[0], array('document_start', 'document_end') ) ) continue;
			    call_user_func_array(array(&$renderer, $instruction[0]), $instruction[1]);
			  }
			}
			$renderer->toc = array_merge($toc, $renderer->toc);
			
			return true;
		}
		if($mode == 'metadata') {
			global $ID;
			// prevent caching to ensure good user data detection for tests
			$renderer->info['cache'] = false;
			
			$blocks = $data[0];
			$content = $data[1];
			
			// parsing content for a <else> statement
			$else = '';
			if(strpos($content, '<else>') !== false) {
				$i = explode('<else>', $content);
				$content = $i[0];
				$else = implode('', array_slice($i, 1));
			}
			
			// Process condition blocks
			$bug = false;
			$this->_loadtester();
			$ok = $this->_processblocks($blocks, $bug);
			// Render content if all went well
			$metatoc = $renderer->meta['description']['tableofcontents'];
			if(!$bug) {
			  $instr = p_get_instructions($ok ? $content : $else);
			  foreach($instr as $instruction) {
			  	if ( in_array($instruction[0], array('document_start', 'document_end') ) ) continue;
			    call_user_func_array(array(&$renderer, $instruction[0]), $instruction[1]);
			  }
			}
			
			if ( !is_array($renderer->meta['description']['tableofcontents']) ) {
				$renderer->meta['description']['tableofcontents'] = array();
			}

			$renderer->meta['description']['tableofcontents'] = array_merge($metatoc, $renderer->meta['description']['tableofcontents']); 
			
			return true;
		}
		return false;
	}
	
	// Strips the heading <p> and trailing </p> added by p_render xhtml to acheive inline behavior
	function _stripp($data) {
		$data = preg_replace('`^\s*<p[^>]*>\s*`', '', $data);
		$data = preg_replace('`\s*</p[^>]*>\s*$`', '', $data);
		return $data;
	}
	
	// evaluates the logical result from a set of blocks
	function _processblocks($b, &$bug) {
		for($i=0; $i<count($b); $i++) {
			if(($b[$i]['type'] == 'block') || ($b[$i]['type'] == 'nblock')) {
				$b[$i]['r'] = $this->_processblocks($b[$i]['value'], $bug);
				if($b[$i]['type'] == 'nblock') $b[$i]['r'] = !$b[$i]['r'];
			}else{
				$b[$i]['r'] = $this->_evaluate($b[$i], $bug);
			}
		}
		if(!count($b)) $bug = true; // no condition in block
		if($bug) return false;
		
		// assemble conditions
		/* CUSTOMISATION :
		 * You can add custom mixing operators here, don't forget to add them to
		 * the "allowedoperators" list at the top of this file
		 */
		$r = $b[0]['r'];
		for($i=1; $i<count($b); $i++) {
			if($b[$i-1]['next'] == '') {
				$bug = true;
				return false;
			}
			switch($b[$i-1]['next']) {
				case '&&' :
				case 'and' :
					$r &= $b[$i]['r'];
					break;
				case '||' :
				case 'or' :
					$r |= $b[$i]['r'];
					break;
				case '^' :
				case 'xor' :
					$r ^= $b[$i]['r'];
					break;
			}
		}
		return $r;
	}
	
	// evaluates a single test, loads custom tests if class exists, default test set otherwise
	function _evaluate($b, &$bug) {
		if(!$this->tester) {
			$bug = true;
			return false;
		}
		return $this->tester->run($b, $bug);
	}
	
	// tries to load user defined tester, then base tester if previous failed
	function _loadtester() {
		global $conf;
		$this->tester = null;
		include_once(DOKU_PLUGIN.'condition/base_tester.php');
		if(@file_exists(DOKU_INC.'lib/tpl/'.$conf['template'].'/condition_plugin_custom_tester.php')) {
			include_once(DOKU_INC.'lib/tpl/'.$conf['template'].'/condition_plugin_custom_tester.php');
			if(class_exists('condition_plugin_custom_tester')) {
				$this->tester = new condition_plugin_custom_tester();
			}
		}
		if(!$this->tester) {
			if(class_exists('condition_plugin_base_tester')) {
				$this->tester = new condition_plugin_base_tester();
			}
		}
	}
} //class
?>
