<?php

/**
 * Simply echo back what is sent
 */

$c_module = function ($input, $arg, $channel, $nick, $socket) {
	
	$chan = ltrim(trim($arg[2]), '#');
	
	if (empty($chan)){
		
		$chan = ltrim(trim($channel), '#');
	
	}
	
	switch($arg[1]){
		
		case 'join':
		case 'j':
			socket_write($socket, "JOIN #{$chan}" . PHP_EOL);
		break;
		
		case 'part':
		case 'p':
			socket_write($socket, "PART #{$chan} :Requested by {$nick}" . PHP_EOL);
		break;
		
		default:
			return "Invalid user of 'o' command.";
		break;
		
	}
	
	
};

?>