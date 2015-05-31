<?php

class MC
{
	protected static $_instance = null;
	protected static $_sharded_keys = null;

	protected static $_all_instances = null;

	private static function GetHosts()
	{
		//@todo: You'll need to update this line
		return array("memcached1.yourapp.com", "memcached2.yourapp.com");
	}

	private static function Setup()
	{
		if (self::$_instance == null) {
			$hosts = self::GetHosts();

			$memcached = new Memcached();
			foreach($hosts as $host)
			{
				$memcached->addServer($host, 11211,1);
			}
			
			self::$_instance = $memcached;
		}
	}

	private static function SetupAllInstances()
	{
		if(self::$_all_instances == null)
		{
			self::$_all_instances = array();
			$hosts = self::GetHosts();
			foreach($hosts as $host)
			{
				$memcached = new Memcached();
				$memcached->addServer($host, 11211,1);
				self::$_all_instances[] = $memcached;
			}
		}

	}

	private static function IsShardedKey($key)
	{
		if(self::$_sharded_keys == null)
		{
			//@todo: Add the prefixes of your sharded keys to this array
			self::$shard_keys = array(
					'BiasKey1Prefix::',
					'BiasKey2Prefix::',
					'BiasKey3Prefix::',
					);

		}

		foreach(self::$_shard_keys as $needle) {
			if (strpos($key,$needle) !== false) {
				return true;
			}
		}

		return false;
	}


	public static function Get($key)
	{
		if(self::IsShardedKey($key))
		{
			self::SetupAllInstances();

			//Pick a random server to perform the get against as the key is available on all the servers
			$random_index = rand(0,count(self::$_all_instances) -1);
			return self::$_all_instances[$random_index]->get($key);
		}
		else
		{
			//PHP handles the key distribution in this case
			self::Setup();
			return self::$_instance->get($key);
		}
	}

	public static function Set($key, $object, $timeout = 300)
	{
		if(self::IsShardedKey($key))
		{
			//Set the key on all the memcache servers
			self::SetupAllInstances();
			foreach(self::$_all_instances as $mc)
			{
				$value = $mc->Set($key,$object,$timeout);
				if($value === false)
					error_log("Memcached set failure. Key: $key");
			}
		}
		else
		{
			//Allow PHP to distribute the key to one of the memcached servers
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
	}

	public static function Delete($key)
	{
		if(self::IsShardedKey($key))
		{
			self::SetupAllInstances();
			foreach(self::$_all_instances as $mc)
			{
				$value = $mc->Delete($key);
				if($value === false)
					error_log("Memcached set failure. Key: $key");
			}
		}
		else
		{
			self::Setup();
			$value = self::$_instance->delete($key);
			if($value === false)
				error_log("Memcached delete failure. Key: $key");
		}
	}
}
