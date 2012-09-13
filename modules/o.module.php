<?php

/**
 * Simply echo back what is sent
 */

$o_module = function ($input, $arg, $channel, $nick, $socket) {
	
	switch($arg[1]){
		
		case 'oper':
		case 'op':
			socket_write($socket, "MODE {$channel} +o {$arg[2]}" . PHP_EOL);
		break;
		
		case 'deoper':
		case 'deop':
			socket_write($socket, "MODE {$channel} -o {$arg[2]}" . PHP_EOL);
		break;
		
		case 'halfoper':
		case 'halfop':
		case 'hop':
			socket_write($socket, "MODE {$channel} +h {$arg[2]}" . PHP_EOL);
		break;
		
		case 'dehalfoper':
		case 'dehalfop':
		case 'dehop':
			socket_write($socket, "MODE {$channel} -h {$arg[2]}" . PHP_EOL);
		break;
		
		case 'voice':
		case 'v':
			socket_write($socket, "MODE {$channel} +v {$arg[2]}" . PHP_EOL);
		break;
		
		case 'devoice':
		case 'dev':
			socket_write($socket, "MODE {$channel} -v {$arg[2]}" . PHP_EOL);
		break;
		
		default:
			return "Invalid user of 'o' command.";
		break;
		
	}
	
	
};

?>