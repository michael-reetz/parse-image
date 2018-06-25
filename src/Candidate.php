<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 21.06.2018
 * Time: 19:43
 */
namespace MichaelReetz;
/**
 * Class Candidate
 */
class Candidate
{
	public $character = '';
	public $width = 0;

	/**
	 * Candidate constructor.
	 * @param string $character
	 * @param int $width
	 */
	public function __construct($character, $width)
	{
		$this->character = $character;
		$this->width = $width;
	}

	/**
	 * @param string $acc
	 * @param self $cur
	 * @return string
	 */
	private static function reduce($acc, $cur)
	{
		return $acc . $cur->character;
	}

	/**
	 * @param self[] $candidates
	 * @return string
	 */
	public static function out($candidates)
	{
		return array_reduce($candidates, [self::class, 'reduce'], '');
	}
}






