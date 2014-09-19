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

use Illuminate\Database\Capsule\Manager as LaravelDb;

class SessionTest extends PHPUnit_Framework_TestCase
{
	public function testDefaultSession()
	{
		// Create a blank sqlite db
		touch('/tmp/gears-session-test.db');

		// Grab a laravel db connection
		$capsule = new LaravelDb;
		$capsule->addConnection
		([
			'driver'    => 'sqlite',
			'database'  => '/tmp/gears-session-test.db',
			'prefix'    => ''
		]);
		$db = $capsule->getConnection('default');

		// Get a new guzzle client
		$http = GuzzleTester();

		// Make an intial request
		$response = $http->get();

		print_r($response->json());

		// Check to see if the db schema is valid
		$this->assertTrue($db->getSchemaBuilder()->hasTable('sessions'));

		// THE FOLLOWING ARE COMMENTED OUT UNTILL LARAVEL FIX SQLITE BUG
		//$this->assertTrue($db->getSchemaBuilder()->hasColumn('sessions', 'id'));
		//$this->assertTrue($db->getSchemaBuilder()->hasColumn('sessions', 'payload'));
		//$this->assertTrue($db->getSchemaBuilder()->hasColumn('sessions', 'last_activity'));

		// TODO: Add more checks of the db schema, like column types, etc

		// Check for the session cookie
		$this->assertContains
		(
			'gears-session',
			$response->getHeader('Set-Cookie', true)[0]
		);

		// Check for the session csrf value
		$this->assertArrayHasKey('_token', $response->json());

		// Check that we have only one bar
		$this->assertEquals(['bar'], $response->json()['foo']);

		// Make a new request
		$response = $http->get();

		print_r($response->json());

		// Now we should have 2 bars
		$this->assertEquals(['bar', 'bar'], $response->json()['foo']);

		// Clean up, delete the tmp db
		unlink('/tmp/gears-session-test.db');
	}
}