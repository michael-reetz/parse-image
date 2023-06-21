<?php
/**
 * @copyright 2018 Michael Reetz
 * @license   read /LICENSE
 * @link      http://www.reetzclan.de
 */

namespace MichaelReetz;

trait Singleton
{
	static private $instance;

	/**
	 * Protect from creation through new Singleton
	 */
	private function __construct()
	{
		/* ... @return Singleton */
	}

	/**
	 * Protect from creation through clone
	 */
	private function __clone()
	{
		/* ... @return Singleton */
	}

	/**
	 * Protect from creation through unserialize
	 */
	private function __wakeup()
	{
		/* ... @return Singleton */
	}

	/**
	 * @return object|null
	 */
	public static function getInstance(): ?object
	{
		return self::$instance ?? (self::$instance = new static());
	}
}
