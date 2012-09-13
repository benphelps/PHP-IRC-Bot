<?php

/**
 * PHIRC - PHP IRC Bot
 * 
 * Connects to an IRC network to monitor,
 * interact, receive, and send messages.
 *
 * This bot is coded and test to work with
 * Inspircd IRC networks and to run on CentOS.  
 * How it works with other IRC software is
 * and other OS's is unknown to me.
 * 
 * I take no legal responsibility over this code.
 * 
 * @author Ben Phelps
 * @copyright Ben Phelps 2009
 * @license (CC) BY-NC-SA
 */

error_reporting(E_ALL);

ini_set('zlib.output_compression', 0);

// we never stop, that wouldn't be good for a loop
set_time_limit(0);

// include the colors class and debug function
include('modules/colors.class.php');

// set debug to true for terminal output
$debug = true;

// 1 = Connection/Command, 2 = Connection/Command/Input, 3 = Everything (very verbose)
$debugLevel = 3;

// you can change this if your OS needs
// a different line break, works fine
// like it is under linux
define("NL", "\n");

if ($debug) debug ( "PHP IRC Bot - http://benphelps.me/" . NL );

$IRChost   = "chat.freenode.com"; // host or IP of the server
$IRCport   = '6667'; // port, leave 6667 if your not sure
$IRCchan   = array("bots"); // an array of channels to join

$BotNick   = "PHIRC-Bot"; // the nickname, shows in chat, remove the random
$BotName   = "PHP IRC Bot"; // real name, ex. Bens Bot
$BotHost   = "benphelps.me"; // Bot Host, my.bot.mysite.com
$BotIdent  = $BotNick; // Should be the same as nick
$BotPre    = '!';

// set this to the password used in "/msg NickServ identify <password>"
// if set, the bot will try to login, if not, its passed
$BotPass   = ""; 


$ignore_nick = array();
$ignore_host = array();


// modules to preload, this are loaded before just before the loop start
$preload = array('say', 'rot13');

// the time to wait before joining channels, 3 seconds is a safe wait time
$wait_time = 3;

// create a new socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_block($socket);
socket_connect($socket, $IRChost, $IRCport);

if ($debug&&$socket) debug ( "Socket Open" . NL );
if ($debug&&!$socket) debug ( "Could not open Socket connection!" . NL );

//Sends USER HOSTNAME IDENT :REAL NAME
socket_write($socket, "USER $BotNick $BotHost $BotIdent :$BotName".NL);
if ($debug) debug ( "Sending USER" . NL );

socket_write($socket, "NICK $BotNick".NL);
if ($debug) debug ( "Sending NICK" . NL );


// wait to alllow the IRC server to do its part
// without this, you will have a hard time joining channels
if ($debug) debug ( "Waiting" );
for($wait=0;$wait < $wait_time;$wait++) {
	debug( "." , FALSE );
	sleep(1);
}
if ($debug) debug ( "Done" . NL, FALSE );

// Login to nickserv
if(!empty($BotPsss)){
	if ($debug) debug ( "Logging In" . NL );
	socket_write($socket, "PRIVMSG NickServ :identify $BotPass".NL);
}


//Join the channels
foreach($IRCchan as $IRCjoin){
	socket_write($socket,"JOIN #".$IRCjoin."".NL);
	if ($debug) debug ( "Joined: #" . $IRCjoin . NL );
}

// and array to store modules that are loaded
$mod_store = array();

// load any pre-load modules
foreach($preload as $module){
	$mod = "modules/". $module . ".module.php";
	if(file_exists($mod)){

		$before = memory_get_usage();
		include($mod);
		$after = memory_get_usage();

		if(isset(${$module."_module"})){
			$mem_usage = number_format(($after - $before) / 1024, 2);
			$mod_store[] = $module;
			if ($debug) debug ( "Loaded module '{$module}'. Module is using {$mem_usage}kb of memory. " . NL, true, 'green'  );
		}
		else {
			if ($debug) debug ( "Tried to load malformed module '{$module}'" . NL, true, 'red' );	
		}

	}
	else{
		if ($debug) debug ( "Tried to load missing module '{$module}'" . NL, true, 'red' );	
	}
}



// set the socket to nonblocking, since we don't wait on input, we keep going
socket_set_nonblock($socket);

// Looooop, WeeeeEeeEEeEEEee
if ($debug) debug ( "Entering Loop!" . NL );
while (TRUE) {
	
	
	// read what the server has to say
	while (@socket_recv($socket, $sread, 10240, MSG_DONTWAIT)) {
		
		if ($debug&&$debugLevel>2) debug ( $sread, FALSE, "light_blue" );
		
		// if sread is empty, nothing to parse, then we dont need this
		if(!empty($sread)){
			
			if ($debug&&$debugLevel>2) debug ( $sread, FALSE, "light_blue" );
			
	   		// we need this exploded raw data to answer a ping / version request
	   		$raw = explode(' ', $sread);
	
	   		if (trim($raw[0]) == "PING") {
	   			if ($debug) debug ( "Got PING.. " );
	   			// the server sends PING and wants a PONG back
	   			// this makes sure the client is still connected
	   			socket_write($socket, "PONG ".$raw[1].NL);
	   			if ($debug) debug ( "Sending PONG" . NL, FALSE );
	   		}
	
	   		// this is a bit crazy, but it works [{$BotPre}]
	   		// we parse it with regex, big thanks to <thesaxfan> on FreeNode's ##php and http://gskinner.com/RegExr/ for making is so much more simple
	   		preg_match("/:(?P<nick>[^!]*)[!](?P<host>[^\s]*)[\s](?P<action>[^\s]*)[\s](?P<chan>[^\s]*)\s[:][{$BotPre}](?P<cmd>[^\s]*)(?P<msg>.*)/", trim($sread), $data);
	
	   		// after this you have
	   		// $data['nick']   = Nick of the person sending the msg
	   		// $data['host']   = The users hostname "user@hostname"
	   		// $data['action'] = The action, should always be PRIVMSG
	   		// $data['chan']   = The channel the message was sent to
	   		// $data['cmd']    = The Bot CMD that was sent
	   		// $data['msg']    = The message sent
	   		
	   		// if its not empty, then we have a go line
	   		// lets get this started
	   		if(!empty($data)){
	   			
	   			// we build some vars that will be used later on
				$channel	= $data['chan']; // the current channel
	   			$SL			= "PRIVMSG $channel :"; // for sending to the channel
				$MODE		= "MODE $channel "; // for settings modes
	   			$nick		= trim($data['nick']); // the person saying it
	   			$cmd 	    = trim($data['cmd']); // the cmd they issued
	   			$msg		= trim($data['msg']); // what was after the cmd
				$UL			= "PRIVMSG $nick :"; // for sending PM to the user who requested
				
				
	   			
	   			// you can change the explode count to match the ammount of returned vars you need
	   			$argex   = explode(' ', $msg, 3); // we explode the msg
	   			$arg[1]  = (!empty($argex[0])?trim($argex[0]):NULL); // this could be a arg in future commands
	   			$arg[2]  = (!empty($argex[1])?trim($argex[1]):NULL); // dido
	   			$arg[3]  = (!empty($argex[2])?trim($argex[2]):NULL); // this would be remanding text
	
				if ($debug&&$debugLevel>1) debug (  $channel . " - " . $nick . " - " . $cmd . NL );
	   			
	   			// make a switch based on the cmd sent to us
	
				$cmd = $BotPre . $cmd;
				
	   			switch($cmd){
	   				
	            
					// Send bot Stats
					case $BotPre . "stats":
					
						$current = number_format(memory_get_usage() / 1024, 3);
						$peak = number_format(memory_get_peak_usage() / 1024, 3);
						
						$output = "Current Memory Usage: " . $current . "kb - Peak Memory Usage: " . $peak . " kb";
	   					socket_write($socket, $SL."$output".NL);
                
					break;
	   				
					// Close the script
	   				case $BotPre . "die":
	   				
	   					if ($debug) debug ( "Bot Restart" . NL );
	   				
	   					// send some text to the chat room
	   					$output = "Goodbye, I am no longer needed :(";
	   					socket_write($socket, $SL."$output".NL);
	   					// send the IRC quit cmd that lest the server know where leaving
	   					$output = "QUIT Forced Death";
	   					socket_write($socket, $output.NL);
	   					// close the socket
	   					socket_shutdown($socket);
						socket_close($socket);
						unset($socket);
						
						include(__FILE__);
	            
	   				break;
					
	            
					// Close the script
	   				case $BotPre . "restart":
	   				
	   					if ($debug) debug ( "Recived Die Command" . NL );
	   				
	   					// send some text to the chat room
	   					$output = "I'll be rrrrriiiight back....";
	   					socket_write($socket, $SL."$output".NL);
	   					// send the IRC quit cmd that lest the server know where leaving
	   					$output = "QUIT RESTART";
	   					socket_write($socket, $output.NL);
	   					// close the socket
	   					socket_shutdown($socket);
						socket_close($socket);
						unset($socket);
						exec("php ". __FILE__);
	   					// die, this is needed, to end the loop
	   					die("\n");
	            
	   				break;
                
					/**
					 * Module System
					 * Load, reload, unload, modlist
					 */
					
					// Load a module
					case $BotPre . "load":
					
						// set the module name to be loaded
						$load = $arg[1];
						
						// set the module path
						$mod = "modules/". $load . ".module.php";
						
						// check if the module name is in the loaded array
						if(in_array($load, $mod_store)) {
							// module is already loaded, send error
							$output = "Module '{$load}' is already loaded.  Try re-loading it with ' {$BotPre}reload {$load} '";
						}
						else {
							// module wasn't loaded, check if the file exists
							if(file_exists($mod)) {
								
								// get memory usage before the include
								$before = memory_get_usage();
								
								// include the module
								include($mod);
								
								// get memory usage after the include
								$after = memory_get_usage();
								
								// check if the module had the corrent variable format
								if(isset(${$load."_module"})) {
									
									// calcualte the memory usage
									$mem_usage = number_format(($after - $before) / 1024, 2);
										
									// store the module name in the loaded array
									$mod_store[] = $load;
									
									// format an output string saying the module was loaded
									$output = "Loaded Module: {$load}.  Module is using {$mem_usage}kb of memory.";
									
									// print debug to cmd line
									if ($debug) debug ( $nick . " loaded module '{$load}'" . NL, true, 'green'  );
									
								}
								// module variable was not found, so it must be malformed
								else {
									
									// build the error string
									$output = "Malformed Module Format, Check Module File!";
									
									// print debug info
									if ($debug) debug ( $nick . " tried to load malformed module '{$load}'" . NL, true, 'red' );
									
								}
                
							}
							else {
								$output = "Cannot Find Module: " . $arg[1];
								
								if ($debug) debug ( $nick . " tried to load missing module '{$load}'" . NL, true, 'red' );
								
							}
						}
						
						// write message to server
						socket_write($socket, $SL."$output".NL);
					
					break;
					
					// reload a module
					case $BotPre . "reload":
						
						// set the module name to be reloaded
						$load = $arg[1];
						// build the module path
						$mod = "modules/". $load . ".module.php";
						
						// check if the module is loaded
						if(in_array($load, $mod_store))
						{
							// unset the current anon function
							unset(${$load."_module"});
							
							// get the current memory usage
							$before = memory_get_usage();
							
							// include the module
							include($mod);
							
							// get memory usage after module include
							$after = memory_get_usage();
							
							// calculate the memory usage
							$mem_usage = number_format(($after - $before) / 1024, 3);
							
							// build output string saying module was loaded
							$output = "Re-loaded Module: {$load}.  Module is using {$mem_usage}kb of memory.";
							
							// print debug
							if ($debug) debug ( $nick . " re-loaded module '{$load}'" . NL, true, 'green' );
							
						}
						// module was not loaded
						else 
						{
							// print that the module was not loaded
							$output = "Module '{$load}' is not loaded.  Try loading it with ' {$BotPre}load {$load} '";
						}
						
						// write message to server
						socket_write($socket, $SL."$output".NL);
					
					break;
					
					// unload a module
					case $BotPre . "unload":
						
						// set the module name to unload
						$unload = $arg[1];
						
						// get the key of the module name in the loaded array
						$key = array_search($unload, $mod_store);
						
						// check if the key exists
						if($key === false) {
							// module was not in the array, so it was not loaded, send error
							$output = "Unable to unload module '{$unload}'.  Module not loaded.";
						}
						// key was in the array
						else {
							
							// nulify the module anon function
							${$unload."_module"} = NULL;
							
							// unset the anon function
							unset($mod_store[$key], ${$unload."_module"});
							
							// set the output saying moudle unloaded
							$output = "Module '{$unload}' unloaded.";
							
							// print debug
							if ($debug) debug ( $nick . " unloaded module '{$unload}'" . NL, true, 'red' );
							
						}
						
						// write output to server
						socket_write($socket, $SL."$output".NL);
					
					break;
					
					// send a a list of loaded modules
					case $BotPre . "modlist":
					
						// set the output
						$output = "Modules Loaded: ";
						
						// loop over each module
						foreach($mod_store as $mod){
							$output .= $mod . ", ";
						}
						
						// trim the last , and add a .
						$output = trim($output, ", ") . ".";
						
						// print debug info
						if ($debug) debug ( $nick . " requested loaded module list " . NL );
						
						// write to the server
						socket_write($socket, $SL."$output".NL);
					
					break;
	   				
					/**
					 * Module Fallback system
					 * Runs module if its loaded, answers CTCP if requested
					 */
					default:
					
						$last = $cmd . $msg;
					
						// check for a user cmd
						if(substr($cmd, 0, 1) == $BotPre){
					    	
							// trim the bot pre arg, and set the module name
							$load = ltrim($cmd, $BotPre);
							
							// set the user input
							$input = $msg;
							
							// check if the module is loaded
							if( isset( ${$load."_module"} ) ) {
								// run the module, passing env variables
								$output = ${$load."_module"}($input, $arg, $channel, $nick, $socket);
								
								// print debug
								if ($debug) debug ( $nick . " used module '{$load}'" . NL );
								
							}
							// module was not loaded
							else {
								// output an error
								$output = "Module '{$load}' is not loaded.  Try loading it with ' {$BotPre}load {$load} '";
							}
							
							// if the output string is empty, we dont need to send anything
							if(!empty($output)){
								// send the output to the server
								socket_write($socket, $SL."$output".NL);
							}
							
						}
						// check for a CTCP request
						elseif(substr($cmd, 0, 1) == chr(1)) {
							
							// set the CTCP marker
							$ctcp_mark = chr(1);
							
							// set the ctcp request, trim invis chars
							$ctcp_req = trim(trim($cmd, $ctcp_mark));
							
							// set the text after the ctcp request, trim invis chars
							$ctcp_follow = trim(trim($msg, $ctcp_mark));
							
							// switch on the ctcp request
							switch($ctcp_req){
								
								// respond to a PING request
								case 'PING':
									$output = $ctcp_mark . "PING " . microtime(true) . $ctcp_mark;
									socket_write($socket, "NOTICE $nick :$output".NL);
									if ($debug) debug ( "CTCP '{$ctcp_req}' request from " . $nick . NL, TRUE, 'brown' );
								break;
								
								// respond to a VERSION request
								case 'VERSION':
									$output = $ctcp_mark . "VERSION PHIRC (0.3b " . PHP_OS . ")" . $ctcp_mark;
									socket_write($socket, "NOTICE $nick :$output".NL);
									if ($debug) debug ( "CTCP '{$ctcp_req}' request from " . $nick . NL, TRUE, 'brown' );
								break;
								
								// respond to a CLIENTINFO request
								case 'CLIENTINFO':
									$output = $ctcp_mark . " VERSION PING CLIENTINFO TIME " . $ctcp_mark;
									socket_write($socket, "NOTICE $nick :$output".NL);
									if ($debug) debug ( "CTCP '{$ctcp_req}' request from " . $nick . NL, TRUE, 'brown' );
								break;
								
							}
							
						}
					
					break;
					
	   			}
				
	   		}
	
			// unset a few vars
	   		unset($arg, $nick, $msg, $output, $cmd, $argex, $raw, $data);
		
		}
		
	}
}

?>