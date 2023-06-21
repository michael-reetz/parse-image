<?php
/**
 * @copyright 2018 Michael Reetz
 * @license   read /LICENSE
 * @link      http://www.reetzclan.de
 */

namespace MichaelReetz;

class Character
{
	public string $character = '';
	public int $width = 0;

	/**
	 * Candidate constructor.
	 * @param string $character
	 * @param int $width
	 */
	public function __construct(string $character, int $width)
	{
		$this->character = $character;
		$this->width = $width;
	}

	/**
	 * @param string $acc
	 * @param self $cur
	 * @return string
	 */
	private function _toString($acc, $cur)
	{
		return $acc . $cur->character;
	}

	/**
	 * @param static[] $characters
	 * @param bool $explain
	 * @return string
	 */
	public static function toString(array $characters, bool $explain = false): string
	{
		$result = array_reduce($characters, [self::class, '_toString'], '');
		$noNope = false;
		if ($explain) {
			echo "toString: $result " . ' (' . count($characters) . ') ' .
				implode(',', array_map(
						static function($a) use (&$noNope){
							if($a->character === ''){
								return str_repeat($a->width < 0 ? '<' : '>', abs($a->width));
							}
							return $a->character;
						},
						$characters
					)
				);
		}
		return $result;
	}

	/**
	 * @param int $acc
	 * @param self $cur
	 * @return int
	 */
	private function _calcWidth(int $acc, Character $cur): int
	{
		return $acc + $cur->width;
	}

	/**
	 * @param Character[] $characters
	 * @return int
	 */
	public static function calcWidth(array $characters): int
	{
		if (empty($characters)){
			return 0;
		}
		$width = array_reduce($characters, [self::class, '_calcWidth'], 0);

		$last = end($characters);
		if ($last->width < 0 ) {
			$width += abs($last->width);
		}

		return $width;

	}
}
