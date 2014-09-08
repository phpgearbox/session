The Session Gear
================================================================================
**Laravel Sessions Standalone**

Okay so by now hopefully you have heard of [Laravel](http://laravel.com/),
the PHP framework that just makes things easy. So first things first full credit
goes to [Taylor Otwell](https://github.com/taylorotwell) for the Session API.

How to Install
--------------------------------------------------------------------------------
Installation via composer is easy:

	composer require gears/session:*

How to Use
--------------------------------------------------------------------------------
In your *legacy* - non Laravel application.
You can use the Laravel Session API like so:

```php
// Make sure you have composer included
require('vendor/autoload.php');

// Install the gears session component
Gears\Session::install
(
	// This must be an array, that decribes a valid db connection.

	// For more info on this see:
	// http://laravel.com/docs/database AND
	// https://github.com/laravel/framework/tree/master/src/Illuminate/Database

	// This array is passed directly to $capsule->addConnection
	[
		'driver'    => 'mysql',
		'host'      => 'localhost',
		'database'  => 'db_name',
		'username'  => 'db_user',
		'password'  => 'abc123',
		'charset'   => 'utf8',
		'collation' => 'utf8_unicode_ci',
		'prefix'    => '',
	]
);
```

And thats it, for more configuration options see the class comments at:
https://github.com/phpgearbox/session/blob/master/Session.php

Now you can use code like the following:

```php
// Storing An Item In The Session
Session::put('key', 'value');

// Push A Value Onto An Array Session Value
Session::push('user.teams', 'developers');

// Retrieving An Item From The Session
$value = Session::get('key');

// Retrieving An Item Or Returning A Default Value
$value = Session::get('key', 'default');
$value = Session::get('key', function() { return 'default'; });

// Retrieving An Item And Forgetting It
$value = Session::pull('key', 'default');

// Retrieving All Data From The Session
$data = Session::all();

// Determining If An Item Exists In The Session
if (Session::has('users'))
{
    //
}

// Removing An Item From The Session
Session::forget('key');

// Removing All Items From The Session
Session::flush();

// Regenerating The Session ID
Session::regenerate();

// Flashing Data
Session::flash('key', 'value');

// Reflashing The Current Flash Data For Another Request
Session::reflash();

// Reflashing Only A Subset Of Flash Data
Session::keep(array('username', 'email'));
```

For more info on the Session API it's self see:
http://laravel.com/docs/session

*NOTE: While the Laravel Session API does provide support for many diffrent
drivers. This package only supports the database driver (for now).*

**WARINING: Do not use the built in native PHP session 
functions and / or the global $_SESSION array**

Our Extra Method: hasExpired
--------------------------------------------------------------------------------
To my current knowledge of laravel, there is no built in way to work out if a
Session has been set but then expired. So in a normal laravel app if you wanted
to display a "Your Session has expired!" message you would need to do some
custom filters or something... see:

http://stackoverflow.com/questions/14688853/check-for-session-timeout-in-laravel

But with *Gears\Session* just call:

```php
if (Session::hasExpired())
{
	echo 'Due to inactivity, your session has expired!';
	echo 'Please <a href="/login">click here</a> to login again.';
}
```

So now for the why?
--------------------------------------------------------------------------------
While laravel is so awesomely cool and great. If you want to pull a feature out
and use it in another project it can become difficult. Firstly you have to have
an innate understanding of the [IoC Container](http://laravel.com/docs/ioc).

You then find that this class needs that class which then requires some other
config variable that is normally present in the IoC when run inside a normal
Laravel App but in your case you haven't defined it and don't really want
to define that value because it makes no sense in your lets say *legacy*
application.

Perfect example is when I tried to pull the session API out to use in wordpress.
It wanted to know about a ```booted``` method, which I think comes from
```Illuminate\Foundation\Application```. At this point in time I already had to
add various other things into the IoC to make it happy and it was the last straw
that broke the camels back, I chucked a coders tantrum, walked to the fridge,
grabbed another Redbull and sat back down with a new approach.

The result is this project.

--------------------------------------------------------------------------------
Developed by Brad Jones - brad@bjc.id.au