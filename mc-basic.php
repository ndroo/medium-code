<?php

class MC
{
	protected static $_instance = null;

	private static function Setup()
	{
		if (self::$_instance == null) {
			//@todo: You'll need to update this line
			$hosts = array("memcached1.yourapp.com", "memcached2.yourapp.com");

			$memcached = new Memcached();
			foreach($hosts as $host)
			{
				$memcached->addServer($host, 11211,1);
			}

			self::$_instance = $memcached;
		}
	}

	public static function Get($key)
	{
		self::Setup();
		return self::$_instance->get($key);
	}

	public static function Set($key, $object, $timeout = 300)
	{
		self::Setup();
		$value = self::$_instance->set($key,$object,$timeout);

		//The standard Memcached implementation returns true/false
		//based on the success or failure of this method
		//I like to ensure we know when it fails as a key not 
		//caching can easily go un-noticed on a qa/staging 
		//environment, but cause serious issues when you deploy 
		//to production
		if($value === false)
			error_log("Memcached set failure. Key: $key");
	}

	public static function Delete($key)
	{
		self::Setup();
		self::$_instance->delete($key);
	}
}
