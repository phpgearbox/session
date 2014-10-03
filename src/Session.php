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

use RuntimeException;
use Gears\Di\Container;
use Illuminate\Database\Capsule\Manager as LaravelDb;
use Illuminate\Session\Store;
use Illuminate\Session\DatabaseSessionHandler;

class Session extends Container
{
	/**
	 * Property: name
	 * =========================================================================
	 * Used to identify the session, the name of the actual session cookie.
	 */
	protected $injectName;

	/**
	 * Property: lifetime
	 * =========================================================================
	 * The time in seconds before garbage collection is run on the server.
	 */
	protected $injectLifetime;

	/**
	 * Property: path
	 * =========================================================================
	 * This is passed directly to setcookie.
	 * See: http://php.net/manual/en/function.setcookie.php
	 */
	protected $injectPath;

	/**
	 * Property: domain
	 * =========================================================================
	 * This is passed directly to setcookie.
	 * See: http://php.net/manual/en/function.setcookie.php
	 */
	protected $injectDomain;

	/**
	 * Property: secure
	 * =========================================================================
	 * This is passed directly to setcookie.
	 * See: http://php.net/manual/en/function.setcookie.php
	 */
	protected $injectSecure;

	/**
	 * Property: dbConfig
	 * =========================================================================
	 * This is an array of configuration data that can be used to create the
	 * dbConnection. This **must** be injected, if you do not inject your very
	 * own dbConnection.
	 */
	protected $injectDbConfig;

	/**
	 * Property: table
	 * =========================================================================
	 * The name of the database table to use for session storage.
	 */
	protected $injectTable;

	/**
	 * Property: dbConnection
	 * =========================================================================
	 * An instance of ```\Illuminate\Database\Connection```.
	 */
	protected $injectDbConnection;

	/**
	 * Property: databaseSessionHandler
	 * =========================================================================
	 * An instance of ```Illuminate\Session\DatabaseSessionHandler```.
	 */
	protected $injectDatabaseSessionHandler;

	/**
	 * Property: sessionStore
	 * =========================================================================
	 * An instance of ```Illuminate\Session\Store```.
	 */
	protected $injectSessionStore;

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
	private static $instance;

	/**
	 * Method: setDefaults
	 * =========================================================================
	 * This is where we set all our defaults. If you need to customise this
	 * container this is a good place to look to see what can be configured
	 * and how to configure it.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	protected function setDefaults()
	{
		$this->name = 'gears-session';

		$this->table = 'sessions';

		$this->lifetime = 120;

		$this->path = '/';

		$this->dbConnection = function()
		{
			if (!is_array($this->dbConfig))
			{
				throw new RuntimeException
				(
					'Invalid Database Connection Provided'
				);
			}

			$capsule = new LaravelDb;
			$capsule->addConnection($this->dbConfig);
			return $capsule->getConnection('default');
		};

		$this->databaseSessionHandler = function()
		{
			return new DatabaseSessionHandler
			(
				$this->dbConnection,
				$this->table
			);
		};

		$this->sessionStore = function()
		{
			return new Store
			(
				$this->name,
				$this->databaseSessionHandler
			);
		};
	}

	/**
	 * Method: install
	 * =========================================================================
	 * Once the container has been configured. Please call this method to
	 * install the session api into your application.
	 *
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $global: If set to true we will also run globalise after setup.
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function install($global = false)
	{
		// Make sure we have a sessions table
		$schema = $this->dbConnection->getSchemaBuilder();
		if (!$schema->hasTable($this->table))
		{
			$schema->create($this->table, function($t)
			{
				$t->string('id')->unique();
				$t->text('payload');
				$t->integer('last_activity');
			});
		}

		// Run the garbage collection
		$this->sessionStore->getHandler()->gc($this->lifetime);

		// Check for our session cookie
		if (isset($_COOKIE[$this->name]))
		{
			// Grab the session id from the cookie
			$cookie_id = $_COOKIE[$this->name];

			// Does the session exist in the db?
			$session = (object) $this->dbConnection
				->table($this->table)
				->find($cookie_id)
			;

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

		// Run globalise
		if ($global) $this->globalise();
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
	 * - $destroy: If set to true the previous session will be deleted.
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
	 * Now in a normal laravel application you can call the session api like so:
	 * 
	 * ```php
	 * Session::push('key', 'value');
	 * ```
	 * 
	 * This is because laravel has the IoC container with Service Providers and
	 * Facades and other intresting things that work some magic to set this up
	 * for you. Have a look in you main app.php config file and checkout the
	 * aliases section.
	 * 
	 * If you want to be able to do the same in your
	 * application you need to call this method.
	 *
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * - $alias: This is the name of the alias to create. Defaults to Session.
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * - RuntimeException: When a class of the same name as the alias
	 *   already exists.
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
			throw new RuntimeException('Class already exists!');
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
	 * - $name: The name of the method to call.
	 * - $args: The argumnent array that is given to us.
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
	 * - $name: The name of the method to call.
	 * - $args: The argumnent array that is given to us.
	 *
	 * Returns:
	 * -------------------------------------------------------------------------
	 * mixed
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 * - RuntimeException: When we have not been globalised.
	 */
	public static function __callStatic($name, $args)
	{
		if (empty(self::$instance))
		{
			throw new RuntimeException('You need to run globalise first!');
		}

		return call_user_func_array([self::$instance, $name], $args);
	}
}