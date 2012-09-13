<?php

/**
 * This is a simple example module, showing the module format.
 * Creating modules is very simple.
 */

$rot13_module = function ($input) {
	
	$rot13 = str_rot13($input);
	return $rot13;
	
};

/*

// Here is an example module format

$[module_name]_module = function ($input) {
	
	$output = modifyInput($input);
	return $output;
	
};

*/

?>