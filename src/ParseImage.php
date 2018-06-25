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

			// speculate that a left-oversize character might be here
			$res = $this->testAll($image, $x, true);
			if ($res !== false) {
				$result .= $res;
				continue;
			}
			throw new \Exception("not found at pos $x of file $filename");
		}
		$this->debug->echoString("File $filename -> $result \n");
		return $result;
	}

	/**
	 * @param resource $image
	 * @param int $x
	 * @param bool $nextBleedThrough
	 * @return bool|string
	 */
	private function testAll($image, &$x, $nextBleedThrough = false)
	{
		foreach ($this->characters as $testWidth => $letters) {
			foreach ($letters as $char => $testImage) {
				$this->debug->echoString("$char ", 3);
				if ($this->test($image, $testImage, $x, $testWidth, $nextBleedThrough)) {
					$this->debug->echoString("Found at $x ", 3);
					$x += $testWidth;
					$this->debug->echoString("continue $x \n", 3);
					return $char;
				}
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
	 * @param bool $nextBleedThrough
	 * @return bool
	 */
	public function test($image, $testImage, $xStart, $width, $nextBleedThrough = false)
	{
		for ($x = 0; $x < $width; $x++) {
			for ($y = 0; $y < $this->height; $y++) {
				$mightBeBleedTrough = ($x == $width - 1) && ($y == $this->height - 2);
				$a = imagecolorat($image, $x + $xStart, $y);
				$b = imagecolorat($testImage, $x, $y);
				if ($a != $b) {
					if (
						!$nextBleedThrough
						||
						!$mightBeBleedTrough
					) {
						return false;
					}
				}
			}
		}
		return true;
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






