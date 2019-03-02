<?php 

require 'simple_html_dom.php';

/**
 * Evaluate the formula in JS Challenge code
 * $formula - The JS forumat extracted from HTML 
 **/
function cf_formula_calculate($formula){
	$formula = str_replace(array('!+[]', '+!![]', '+![]'),array('+1','+1','+1'),$formula);
	$formula = str_replace(array('+[]'),array('+0'),$formula);
	$formula = str_replace(array(')+(',')/+('),array(').(',')/('),$formula);

	return eval('return ' . $formula . ';');
}

/**
 * Extract the JS code and solve the challenge to get answer value
 * $content - HTML content of challenge page
 **/
function cf_bypass_solve_challenge($content){
	preg_match('/setTimeout\(function\(\)\{(.*)\}, 4000\);/s', $content, $matches);
	
	$main = $matches[1];
	$lines = explode(';', $main);
	$p1 = $lines[0];
	
	// find the variable name first
	preg_match('/, (.*)={"(.*)":/', trim($p1), $matches);
	$variable = $matches[1] . '.' . $matches[2];
	
	// find first formula
	preg_match('/":(.*)\}/', $p1, $matches);
	
	$formula1 = $matches[1];
	
	$answer = cf_formula_calculate($formula1);
	
	$operator = array('-=','*=','+=');
	foreach($lines as $line){
		$lines = trim($line);
		
		if($line == '' || strpos($line, $variable) === false) continue;
		
		foreach($operator as $op){
			if(strpos($line, $variable . $op) !== false){
				$formula = str_replace($variable . $op, '', $line);
				switch($op){
					case '-=':
						$answer -= cf_formula_calculate($formula);
						break;
					case '*=':
						$answer *= cf_formula_calculate($formula);
						break;
					case '+=':
						$answer += cf_formula_calculate($formula);
						break;
				}
			}
		}
	}
	
	// 15 is the domain length of novelplanet.com		
	$answer = 15 + round($answer, 10);
	
	return $answer;
}

/**
 * Get page content with cf_clearance cookie well known
 * $url - The URL you want to get content
 * $host - Root URL of the site you want to get content. In fact, $host can be retrieved from $url
 *
 **/
function cf_get_content($url, $host){
	$cookie_path = 'cf_clearance.cookie'; // we save cookie in file to use later

	$cookies = '';
	if(file_exists($cookie_path)){
		$cookies = file_get_contents($cookie_path);
	}
	$cf_clearance = '';
	$cf_uid = '';
	
	if($cookies != ''){
		$cookies = json_decode($cookies);
		$cf_clearance = $cookies->cf_clearance;
		$cf_uid = $cookies->cf_uid;
	}
	
	$found = false; // the flag
	$max_try = 5; // maximum number of tries is 5. We stop after 5 fail tries.
	$try = 1; // the index

	global $need_cookies_response, $cookies, $request_cookies, $request_referer;
	
	$need_cookies_response = true;
	if($cf_clearance != ''){
		$need_cookies_response = false;
		$request_cookies = array('cf_clearance' => $cf_clearance, '__cfduid' => $cf_uid);
	}
	
	$html = file_get_html($url);
	
	$form = $html->find('#challenge-form', 0);
	if($form){
		while(!$found){
			// need to bypass challenge
			$answer = cf_bypass_solve_challenge($html);
			$action = $form->action;
			$inputs = $form->find('input');
			$s = '';
			$jschl_vc = '';
			$pass = '';
			foreach($inputs as $input){
				if($input->name == 's'){
					$s = $input->value;
				}
				if($input->name == 'jschl_vc'){
					$jschl_vc = $input->value;
				}
				if($input->name == 'pass'){
					$pass = $input->value;
				}
			}
			
			$args = array(
						's' => $s,
						'jschl_vc' => $jschl_vc,
						'pass' => $pass,
						'jschl_answer' => $answer
					);
					
			$query = '';
			foreach($args as $key => $val){
				$query .= '&' . $key . '=' . $val;
			}
			
			$get = $host . $action . '?' . http_build_query($args);
			
			$request_referer = $url;
			$request_cookies = $cookies;
			
			$need_cookies_response = true;
			
			// Cloudflare needs 5 seconds delay
			sleep(6);
			file_get_html($get);
			
			if(isset($cookies['cf_clearance'])){
				$the_cookie = array('cf_clearance' => $cookies['cf_clearance'], 'cf_uid' => isset($request_cookies['__cfduid']) ? $request_cookies['__cfduid'] : '');
				
				// save it to use later
				file_put_contents($cookie_path, json_encode($the_cookie));
				
				$found = true;
				
				$request_cookies = array('cf_clearance' => $cookies['cf_clearance'], '__cfduid' => $request_cookies['__cfduid']);
				$need_cookies_response = false;
				$html = file_get_html($url);
				
				return $html;
			} else {
				if($try >= $max_try){
					break;
				} else {
					$try++;
				}
			}
		}
		
		if(!$found){
			return '';
		}
	} else {
		// no challenge form. get content directly
		return $html;
	}
}