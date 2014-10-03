<?php
////////////////////////////////////////////////////////////////////////////////
// __________ __             ________                   __________              
// \______   \  |__ ______  /  _____/  ____ _____ ______\______   \ _______  ___
//  |     ___/  |  \\____ \/   \  ____/ __ \\__  \\_  __ \    |  _//  _ \  \/  /
//  |    |   |   Y  \  |_> >    \_\  \  ___/ / __ \|  | \/    |   (  <_> >    < 
//  |____|   |___|  /   __/ \______  /\___  >____  /__|  |______  /\____/__/\_ \
//                \/|__|           \/     \/     \/             \/            \/
// -----------------------------------------------------------------------------
//          Designed and Developed by Brad Jones <brad @="bjc.id.au" />         
// -----------------------------------------------------------------------------
////////////////////////////////////////////////////////////////////////////////

// Load the composer autoloader
require('../../vendor/autoload.php');

// Create a new session object
$session = new Gears\Session();

// Configure the session object
$session->dbConfig =
[
	'driver'    => 'sqlite',
	'database'  => '/tmp/gears-session-test.db',
	'prefix'    => ''
];

// Install the session api
$session->install();

// Add a value to the session
$session->push('foo', 'bar');

// Output the session as json for testing
echo json_encode($session->all());