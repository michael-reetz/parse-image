<?php
/**
 * @copyright 2018 Michael Reetz
 * @license   read /LICENSE
 * @link      http://www.reetzclan.de
 */

namespace MichaelReetz;
/**
 * @method static Debug getInstance()
 */
class Debug
{
	use Singleton;

	/**
	 * @var int
	 */
	private int $verbose = 1;

	/**
	 * @param int $verbose
	 * @return bool
	 */
	private function isVerbose(int $verbose): bool
	{
		return $this->verbose > $verbose;
	}


	/**
	 * @param string $s
	 * @param int $verbose
	 * @return Debug
	 */
	public function echoString(string $s, int $verbose): self
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
	public function varDump($mixed, int $verbose = 10): self
	{
		if ($this->isVerbose($verbose)) {
			var_dump($mixed);
		}
		return $this;
	}

	/**
	 * increases Verbose level
	 */
	public function verbose(): self
	{
		$this->verbose++;
		return $this;
	}
}






