<?php
/**
 * @copyright 2018 Michael Reetz
 * @license   read /LICENSE
 * @link      http://www.reetzclan.de
 */

namespace MichaelReetz;
/**
 * Class Debug
 * @method static Debug getInstance()
 */
class Debug
{
	use Singleton;

	private $verbose = 1;

	/**
	 * @param integer $verbose
	 * @return bool
	 */
	private function isVerbose($verbose)
	{
		return $this->verbose > $verbose;
	}


	/**
	 * @param string $s
	 * @param int $verbose
	 * @return Debug
	 */
	public function echoString($s, $verbose = 1)
	{
		if ($this->isVerbose($verbose)) {
			echo $s;
		}
		return $this;
	}

	/**
	 * @param mixed $mixed
	 * @param int $verbose
	 * @return Debug
	 */
	public function varDump($mixed, $verbose = 10)
	{
		if ($this->isVerbose($verbose)) {
			var_dump($mixed);
		}
		return $this;
	}

	/**
	 * increases Verbose level
	 */
	public function verbose()
	{
		$this->verbose++;
		return $this;
	}
}






