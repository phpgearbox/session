<?php

var_dump(realpath('/tmp'));
print_r(scandir('/tmp'));
exit;
echo file_get_contents('/tmp/gears-session-test.db');
exit;

// Load the composer autoloader
//require('../../vendor/autoload.php');

//touch('/tmp/gears-session-test.db');

// Create a new session object
$session = new Gears\Session
([
	'driver'    => 'sqlite',
	'database'  => '/tmp/gears-session-test.db',
	'prefix'    => ''
]);

// Add a value to the session
$session->push('foo2', 'bar');

// Output the session as json for testing
echo json_encode($session->all());