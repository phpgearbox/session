<?php namespace Gears;
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
use Illuminate\Session\Store;
use Illuminate\Session\DatabaseSessionHandler;

class Session
{
	/**
	 * Property: name
	 * =========================================================================
	 * Used to identify the session, the name of the actual session cookie.
	 */
	private $name = 'gears-session';

	/**
	 * Property: table
	 * =========================================================================
	 * The name of the database table to use for session storage.
	 */
	private $table = 'sessions';

	/**
	 * Property: lifetime
	 * =========================================================================
	 * The time in seconds before garbage collection is run on the server.
	 */
	private $lifetime = 120;

	/**
	 * Property: path
	 * =========================================================================
	 * This is passed directly to setcookie.
	 * See: http://php.net/manual/en/function.setcookie.php
	 */
	private $path = '/';

	/**
	 * Property: domain
	 * =========================================================================
	 * This is passed directly to setcookie.
	 * See: http://php.net/manual/en/function.setcookie.php
	 */
	private $domain = null;

	/**
	 * Property: secure
	 * =========================================================================
	 * This is passed directly to setcookie.
	 * See: http://php.net/manual/en/function.setcookie.php
	 */
	private $secure = null;

	/**
	 * Property: sessionStore
	 * =========================================================================
	 * This is where we store a copy of the actual Laravel Session Store.
	 */
	private $sessionStore = null;

	/**
	 * Property: dbConnection
	 * =========================================================================
	 * This is where we store a copy of \Illuminate\Database\Connection.
	 * Or at least an object that extends: \Illuminate\Database\Connection
	 */
	private $dbConnection = null;

	/**
	 * Property: expired
	 * =========================================================================
	 * We have added in some extra functionality. We can now easily check to
	 * see if the session has expired. If it has we reset the cookie with a
	 * new id, etc.
	 */
	private $expired = false;

	/**
	 * Property: instance
	 * =========================================================================
	 * This is used as part of the globalise functionality.
	 */
	private static $instance = null;

	/**
	 * Method: __construct
	 * =========================================================================
	 * To setup the Laravel Session, call this method with at the very least a
	 * db connection config array. We explicity use the Database Session Handler
	 * provided by Laravel. This package current does not cater for all the
	 * other session drivers that Laravel normally supports.
	 *
	 * Example usage:
	 *
	 *     new Gears\Session
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
	 * $db - Is an array that describes a database connection. Or you supply
	 * your own instance of \Illuminate\Database\Connection.
	 * 
	 * For examples of the array see:
	 * https://github.com/laravel/laravel/blob/master/app/config/database.php
	 *
	 * $options - An array of other options to set. The keys of the array
	 * reflect the names of the properties above.
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function __construct($db, $options = array())
	{
		// Have we been given an array of db configuration?
		if (is_array($db))
		{
			// Okay so lets make our own laravel db object
			$capsule = new LaravelDb;
			$capsule->addConnection($db);
			$this->dbConnection = $capsule->getConnection('default');
		}

		// Make sure `db` extends \Illuminate\Database\Connection
		elseif (is_a($db, '\Illuminate\Database\Connection'))
		{
			$this->dbConnection = $db;
		}

		// Opps bail out, we can't continue without a valid db connection
		else
		{
			throw new \Exception('Invalid Database Connection Provided');
		}

		// Set the rest of our config
		// Sensible defaults have been set in the properties above
		// So if nothing gets set here, we can continue.
		foreach ($options as $option_key => $option_value)
		{
			if (isset($this->{$option_key}))
			{
				$this->{$option_key} = $option_value;
			}
		}

		// Make sure we have a sessions table
		if (!$this->dbConnection->getSchemaBuilder()->hasTable($this->table))
		{
			$this->dbConnection->getSchemaBuilder()->create($this->table, function($t)
			{
				$t->string('id')->unique();
				$t->text('payload');
				$t->integer('last_activity');
			});
		}

		// Create the session store
		$this->sessionStore = new Store
		(
			$this->name,
			new DatabaseSessionHandler
			(
				$this->dbConnection,
				$this->table
			)
		);

		// Run the garbage collection
		$this->sessionStore->getHandler()->gc($this->lifetime);

		// Check for our session cookie
		if (isset($_COOKIE[$this->name]))
		{
			// Grab the session id from the cookie
			$cookie_id = $_COOKIE[$this->name];

			// Does the session exist in the db?
			$session = (object) $this->dbConnection->table($this->table)->find($cookie_id);
			if (isset($session->payload))
			{
				// Set the id of the session
				$this->sessionStore->setId($cookie_id);
			}
			else
			{
				// Set the expired flag
				$this->expired = true;

				// NOTE: We do not need to set the id here.
				// As it has already been set by the constructor of the Store.
			}				
		}

		// Set / reset the session cookie
		if (!isset($_COOKIE[$this->name]) || $this->expired)
		{
			setcookie
			(
				$this->name,
				$this->sessionStore->getId(),
				0,
				$this->path,
				$this->domain,
				$this->secure,
				true
			);
		}

		// Start the session
		$this->sessionStore->start();

		// Save the session on shutdown
		register_shutdown_function([$this->sessionStore, 'save']);
	}

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
	public function hasExpired()
	{
		return $this->expired;
	}

	/**
	 * Method: regenerate
	 * =========================================================================
	 * When the session id is regenerated we need to reset the cookie.
	 *
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $destroy - If set to true the previous session will be deleted.
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * boolean
	 */
	public function regenerate($destroy = false)
	{
		if ($this->sessionStore->regenerate($destroy))
		{
			setcookie
			(
				$this->sessionStore->getName(),
				$this->sessionStore->getId(),
				0,
				$this->path,
				$this->domain,
				$this->secure,
				true
			);

			return  true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Method: globalise
	 * =========================================================================
	 * Now in a normal laravel application you can call the
	 * session api like so:
	 * 
	 *     Session::push('key', 'value');
	 * 
	 * This is because laravel has the IoC container with Service Providers and
	 * Facades and other intresting things that work some magic to set this up
	 * for you. Have a look in you main app.php config file and checkout the
	 * aliases section.
	 * 
	 * If you want to be able to do the same in your application you need to
	 * call this method.
	 *
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * $alias - This is the name of the alias to create. Defaults to Session
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function globalise($alias = 'Session')
	{
		// Create the alias name
		if (substr($alias, 0, 1) != '\\')
		{
			// This ensures the alias is created in the global namespace.
			$alias = '\\'.$alias;
		}

		// Check if a class already exists
		if (class_exists($alias))
		{
			// Bail out, a class already exists with the same name.
			throw new \Exception('Class already exists!');
		}

		// Create the alias
		class_alias('\Gears\Session', $alias);

		// Save our instance
		self::$instance = $this;
	}

	/**
	 * Method: __call
	 * =========================================================================
	 * This will pass any unresolved method calls
	 * through to the main session store object.
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
	public function __call($name, $args)
	{
		return call_user_func_array([$this->sessionStore, $name], $args);
	}

	/**
	 * Method: __callStatic
	 * =========================================================================
	 * This will pass any unresolved static method calls
	 * through to the saved instance.
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
		// Check to see if we have been globalised
		if (empty(self::$instance))
		{
			throw new \Exception('You need to run globalise first!');
		}

		// Run the method from the static instance
		return call_user_func_array([self::$instance, $name], $args);
	}
}