<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 21.06.2018
 * Time: 19:43
 */
namespace MichaelReetz;
/**
 * Class ParseImage
 * Written in an night session because the yield of ocr was much too bad for
 * these small but very regular letters.
 */
class ParseImage
{
	private $characters = [];
	private $height = null;
	/** @var Debug */
	private $debug;

	/**
	 * Reads letters to build letter knowledge
	 * ReadEmail constructor.
	 */
	public function __construct()
	{
		$this->debug = Debug::getInstance();
		foreach (scandir(__DIR__ . '/characters') as $file) {
			if (!is_file(__DIR__ . '/characters/' . $file)) {
				continue;
			}
			$this->readCharacterFile($file);
		}
		krsort($this->characters);
		$this->debug->varDump($this->characters);
	}

	/**
	 * first characte is the letter
	 * second might by "-" to tell that the first column of the image might be ignored
	 * optional "_" as second or third a.t.m. only because of windows because a === A for windows ; stupid :-/
	 * @param string $characterFilename
	 */
	private function readCharacterFile($characterFilename)
	{
		$this->debug->echoString("read character file $characterFilename\n", 2);
		$char = substr($characterFilename, 0, 1);
		$image = imagecreatefrompng(__DIR__ . '/characters/' . $characterFilename);
		if ($this->height == null) {
			$this->height = imagesy($image);
		}
		$this->setupCharacter($image, $char);
		if (substr($characterFilename, 1, 1) == '-') {
			$width = imagesx($image);
			$crop = ['x' => 1, 'y' => 0, 'width' => $width - 1, 'height' => $this->height];
			$this->setupCharacter(imagecrop($image, $crop), $char);
		}
	}


	/**
	 * @param resource $image
	 * @param string $character
	 */
	private function setupCharacter($image, $character)
	{
		$width = imagesx($image);
		$this->debug->echoString("add character $character with width $width\n", 2);
		if (!key_exists($width, $this->characters)) {
			$this->characters[$width] = [];
		}
		$this->characters[$width][$character] = $image;
	}

	/**
	 * parses a file for letters and returns the found string
	 * @param string $filename
	 * @return string
	 * @throws \Exception
	 */
	public function read($filename)
	{
		$this->debug->echoString("Parse File $filename \n", 2);
		$result = '';
		$image = imagecreatefrompng($filename);
		$width = imagesx($image);
		$this->height = imagesy($image);
		$x=0;
		while ($x < $width) {
			if ($this->isEmpty($image, $x)) {
				$x++;
				continue;
			}
			$this->debug->echoString("Letter begins at $x\n test: ", 3);

			$res = $this->testAll($image, $x);
			if ($res !== false) {
				$result .= $res;
				continue;
			}

//			// speculate that a left-oversize character might be here
//			$res = $this->testAll($image, $x, true);
//			if ($res !== false) {
//				$result .= $res;
//				continue;
//			}
			throw new \Exception("not found at pos $x of file $filename");
		}
		$this->debug->echoString("File $filename -> $result \n");
		return $result;
	}

	/**
	 * @param resource $image
	 * @param int $x
	 * @param Candidate[] $preList
	 * @return bool|string
	 * @throws \Exception
	 */
	private function testAll($image, &$x, $preList = [])
	{
		$candidates = [];
		$this->debug->echoString("try to find from $x with pre: '" . Candidate::out($preList) . "' \n", 3);
		sleep(1);
		$testX = $x;
		foreach ($preList as $pre) {
			$testX += $pre->width;
		}

		foreach ($this->characters as $testWidth => $letters) {
			foreach ($letters as $char => $testImage) {
				$this->debug->echoString("$char", 3);
				$score = $this->test($image, $testImage, $testX, $testWidth);
				$this->debug->echoString("=$score ", 3);
				if ($score > 0.92) {
					$candidates[] = new Candidate(
						$char, $testWidth
					);
				}
			}
		}


		$this->debug->varDump($candidates, 3);
		sleep(5);

		foreach ($candidates as $candidate) {
			$testX0 = $testX + $candidate->width;
			$testX1 = $testX + $candidate->width - 1;
			$testX2 = $testX + $candidate->width - 2;

			$preListAdd = array_merge($preList, [$candidate]);

			$res0 = $this->testAll($image, $testX0, $preListAdd);
			$res1 = $this->testAll($image, $testX1, $preListAdd);
			$res2 = $this->testAll($image, $testX2, $preListAdd);

			if (
				$res0 !== false && $res1 !== false
				||
				$res1 !== false && $res2 !== false
				||
				$res0 !== false && $res2 !== false
			) {
				throw new \Exception('too much ambiguity for me');
			}
			switch(true) {
				case $res0 !== false:
					$x = $res0;
					return $candidate->character . $res0;
				case $res1 !== false:
					$x = $res1;
					return $candidate->character . $res1;
				case $res2 !== false:
					$x = $res2;
					return $candidate->character . $res2;
				default :
					return false;
			}
		}
		return false;
	}


	/**
	 * check if a letter is at position
	 * @param resource $image
	 * @param resource $testImage
	 * @param integer $xStart
	 * @param integer $width
	 * @return float
	 */
	public function test($image, $testImage, $xStart, $width)
	{
		$cnt = 0;
		$match = 0;
		for ($x = 0; $x < $width; $x++) {
			for ($y = 0; $y < $this->height; $y++) {
				$cnt++;
				$a = imagecolorat($image, $x + $xStart, $y);
				$b = imagecolorat($testImage, $x, $y);
				if ($a == $b) {
					$match++;
				}
			}
		}
		return $match / $cnt;
	}

	/**
	 * test if the current
	 * @param resource $image
	 * @param int $x
	 * @return bool
	 */
	private function isEmpty($image, $x)
	{
		for ($y=0; $y<$this->height; $y++) {
			if (imagecolorat($image, $x, $y) != 0xFFFFFF) {
				return false;
			}
		}
		return true;
	}
}






