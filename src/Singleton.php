<?php
/**
 * @copyright 2018 Michael Reetz
 * @license   read /LICENSE
 * @link      http://www.reetzclan.de
 */

namespace MichaelReetz;

trait Singleton
{
	static private $instance = null;

	/**
	 * Protect from creation through new Singleton
	 * Singleton constructor.
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
	 * @return Singleton|null
	 */
	static public function getInstance()
	{
		return self::$instance === null ? self::$instance = new static() : self::$instance;
	}
}
