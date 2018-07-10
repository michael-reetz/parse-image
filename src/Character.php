<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 21.06.2018
 * Time: 19:43
 */
namespace MichaelReetz;
/**
 * Class Character
 */
class Character
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
	private function _toString($acc, $cur)
	{
		return $acc . $cur->character;
	}

	/**
	 * @param static[] $characters
	 * @param bool $explain
	 * @return string
	 */
	public static function toString($characters, $explain = false)
	{
		$result = array_reduce($characters, [self::class, '_toString'], '');
		$noNope = false;
		if($explain) {
			echo "toString: $result " . ' (' . count($characters) . ') ' .
				implode(',', array_map(
						function($a) use (&$noNope){
							if($a->character === ''){
								return str_repeat($a->width < 0 ? '<' : '>', abs($a->width));
							} else {
								return $a->character;
							}
						}, $characters
					)
				);
		}
		return $result;
	}

	/**
	 * @param integer $acc
	 * @param self $cur
	 * @return integer
	 */
	private function _calcWidth($acc, $cur)
	{
		return $acc + $cur->width;
	}

	/**
	 * @param Character[] $characters
	 * @return integer
	 */
	public static function calcWidth($characters)
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
