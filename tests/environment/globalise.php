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

namespace FooBar
{
	function test()
	{
		// Create a new session object.
		// Note how we are inside another namespace.
		$session = new \Gears\Session
		([
			'driver'    => 'sqlite',
			'database'  => '/tmp/gears-session-test.db',
			'prefix'    => ''
		]);

		// Globalise the session
		$session->globalise();
	}
}

namespace
{
	// Load the composer autoloader
	require('../../vendor/autoload.php');

	// Call the FooBar\test function to create the session
	FooBar\test();

	// Note how we have access to the session api globally
	Session::put('global', true);
	
	// Output the session as json for testing
	echo json_encode(Session::all());
}