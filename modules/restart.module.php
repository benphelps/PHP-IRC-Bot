<?php

/**
 * Simply echo back what is sent
 */

$restart_module = function ($input) {
	
	exec('php phirc.php');
	die();
	
};


?>