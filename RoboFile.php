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

/*
 * Include our local composer autoloader just in case
 * we are called with a globally installed version of robo.
 */
require_once(__DIR__.'/vendor/autoload.php');

class RoboFile extends Robo\Tasks
{
	// The port the built in PHP server will run on for our acceptance testing.
	public static $serverPort = 8000;

	// Method to run our unit / acceptance testing.
	public function test()
	{
		$this->taskServer(self::$serverPort)
			->dir('./tests/environment')
			->background(true)
		->run();

		$this->taskPHPUnit()
			->bootstrap('./tests/bootstrap.php')
			->arg('./tests')
		->run();
	}
}