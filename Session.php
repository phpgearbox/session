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

namespace Gears;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Session\Store;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Encryption\Encrypter;

class Session
{
	/**
	 * Property: sessionStore
	 * =========================================================================
	 * This is where we store a copy of the actual Laravel Session Store.
	 */
	private static $sessionStore = null;

	/**
	 * Property: expired
	 * =========================================================================
	 * We have added in some extra functionality. We can now easily check to
	 * see if the session has expired. If it has we reset the cookie with a
	 * new id, etc.
	 */
	private static $expired = false;

	/**
	 * Method: hasExpired
	 * =========================================================================
	 * Pretty simple, if the session has previously been set and now has been
	 * expired by means of garbage collection on the server, this will return
	 * true, otherwise false.
	 *
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * boolean
	 */
	public static function hasExpired()
	{
		return self::$expired;
	}

	/**
	 * Method: install
	 * =========================================================================
	 * To setup the Laravel Session, call this method with at the very least a
	 * db connection config array. We explicity use the Database Session Handler
	 * provided by Laravel. This package current does not cater for all the
	 * other session drivers that Laravel normally supports.
	 *
	 * Example usage:
	 *
	 *     Gears\Session::install
	 *     (
	 *     		[
	 *     			'driver'    => 'mysql',
	 *     			'host'      => 'localhost',
	 *     			'database'  => 'db_name',
	 *     			'username'  => 'db_user',
	 *     			'password'  => 'abc123',
	 *     			'charset'   => 'utf8',
	 *     			'collation' => 'utf8_unicode_ci',
	 *     			'prefix'    => '',
	 *     		]
	 *     );
	 *
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $dbconfig - Is an array that describes a database connection.
	 * 
	 * For examples see:
	 * https://github.com/laravel/laravel/blob/master/app/config/database.php
	 *
	 * $table - The name of the database table to use to store our sessions.
	 *
	 * $name - The name used for the session cookie.
	 *
	 * $lifetime - This is the number of seconds that a session is valid for on
	 * the server side before the garbage collection runs.
	 *
	 * $path - This is passed directly to setcookie.
	 * http://php.net/manual/en/function.setcookie.php
	 *
	 * $domain - This is passed directly to setcookie.
	 * http://php.net/manual/en/function.setcookie.php
	 *
	 * $secure - This is passed directly to setcookie.
	 * http://php.net/manual/en/function.setcookie.php
	 *
	 * $key - If provided we will use this to encrypt the cookies.
	 *
	 * $cipher - You may optionaly also specfiy what cipher to use.
	 * For more info see: http://laravel.com/docs/security#encryption
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public static function install($dbconfig, $table = 'sessions', $name = 'gears-session', $lifetime = 120, $path = '/', $domain = null, $secure = null, $key = null, $cipher = null)
	{
		// Setup the database connection
		$capsule = new Capsule;
		$capsule->addConnection($dbconfig);
		$db = $capsule->getConnection('default');

		// Make sure we have a sessions table
		if (!$db->getSchemaBuilder()->hasTable($table))
		{
			$db->getSchemaBuilder()->create($table, function($t)
			{
				$t->string('id')->unique();
				$t->text('payload');
				$t->integer('last_activity');
			});
		}

		// Create the session store
		self::$sessionStore = new Store($name, new DatabaseSessionHandler($db, $table));

		// Run the garbage collection
		self::$sessionStore->getHandler()->gc($lifetime);

		// Setup the encrypter
		if (!empty($key))
		{
			$encrypter = new Encrypter($key);
			
			if (!empty($cipher))
			{
				$encrypter->setCipher($cipher);
			}
		}

		// Check for our session cookie
		if (isset($_COOKIE[$name]))
		{
			// Do we have an encrypter?
			if (isset($encrypter))
			{
				// We do so lets try decrypting the cookie
				try
				{
					$cookie_id = $encrypter->decrypt($_COOKIE[$name]);
				}
				catch (\Illuminate\Encryption\DecryptException $e)
				{
					$cookie_id = null;
				}
			}
			else
			{
				// Less secure but easier to setup
				$cookie_id = $_COOKIE[$name];
			}

			// Does the session exist in the db?
			$session = (object) $db->table($table)->find($cookie_id);
			if (isset($session->payload))
			{
				// Set the id of the session
				self::$sessionStore->setId($cookie_id);
			}
			else
			{
				// Set the expired flag
				self::$expired = true;

				// NOTE: We do not need to set the id here.
				// As it has already been set by the constructor of the Store.
			}				
		}

		// Set / reset the session cookie
		if (!isset($_COOKIE[$name]) || self::$expired)
		{
			// Do we have an encrypter?
			if (isset($encrypter))
			{
				setcookie
				(
					$name,
					$encrypter->encrypt(self::$sessionStore->getId()),
					0,
					$path,
					$domain,
					$secure,
					true
				);
			}
			else
			{
				setcookie
				(
					$name,
					self::$sessionStore->getId(),
					0,
					$path,
					$domain,
					$secure,
					true
				);
			}
		}
		
		// Start the session
		self::$sessionStore->start();

		// Save the session on shutdown
		register_shutdown_function([self::$sessionStore, 'save']);

		// Alias ourselves if there isn't already a session class
		if (!class_exists('\Session'))
		{
			class_alias('\Gears\Session', '\Session');
		}
	}

	/**
	 * Method: __callStatic
	 * =========================================================================
	 * Okay so this is the magic method that makes it possible to do this:
	 *
	 *     Session::flash('foo', 'bar');
	 *
	 * For more info on how the laravel session api works, please see:
	 * http://laravel.com/docs/session
	 *
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $name - The name of the method to call.
	 * $args - The argumnent array that is given to us.
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * mixed
	 */
	public static function __callStatic($name, $args)
	{
		return call_user_func_array([self::$sessionStore, $name], $args);
	}
}