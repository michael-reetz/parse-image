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
	public  $score;

	/**
	 * Candidate constructor.
	 * @param string $character
	 * @param int $width
	 * @param float $score
	 */
	public function __construct($character, $width, $score)
	{
		$this->character = $character;
		$this->width = $width;
		$this->score = $score;
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

	/**
	 * @param self $a
	 * @param self $b
	 * @return int
	 */
	private static function _sort($a, $b)
	{
		if ($a->score == $b->score) {
			return $a->width > $b->width ? -1 : 1;
		} else {
			return $a->score > $b->score ? -1 : 1;
		}
	}

	/**
	 * @param self[] $candidates
	 * @return string
	 */
	public static function sort(&$candidates)
	{
		return usort($candidates, [self::class, '_sort']);
	}




}






