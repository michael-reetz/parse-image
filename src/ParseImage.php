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

	/** @var resource */
	private $imageToParse = null;

	/** @var resource */
	private $imageForComparison = null;

	/** @var integer */
	private $backgroundColor = null;

	/** @var int */
	private $width = 0;

	/** @var float */
	private $lastEcho = 0;

	/** @var int */
	private $maxX = 0;

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
	 * @param Character[] $characters
	 * @param int $nested
	 * @return Character[]
	 * @throws \Exception
	 */
	private function testCharacters($characters, $nested = 0)
	{
		$nested++;
		$errorPixels = $this->calcErrorPixel($characters);
		if (
			($errorPixels == 0)
			&&
			($this->maxX == ($this->width - 1))
		) {
			return $characters;
		}
		if ($errorPixels > 5) {
			return [];
		}
		if ($nested > 80) {
			echo "ARRRRGGGGG $nested 80 überschritten!";
			echo "\t ERRORPIXELS : ${errorPixels}\n";
			echo "\t Width : " . $this->width . "\n";
			exit;
		}

		$testing = [];
		// testen jedes Buchstaben
		foreach ($this->characters as $testWidth => $letters) {
			foreach ($letters as $char => $testImage) {
				$character = new Character($char, $testWidth);
				if ($testWidth > 2) {
					$test = $characters;
					$test[] = new Character('', -1);
					$test[] = $character;
					$testing[] = $this->testCharacters($test, $nested);
				}
				$test = $characters;
				$test[] = $character;
				$testing[] = $this->testCharacters($test, $nested);
			}
		}
		// alle verworfenen Ketten rauswerfen
		$testing = array_filter($testing, function ($a) {
			return !empty($a);
		});
		// dieses Array dürfe nur noch länge 1 haben????
		if (empty($testing)) {
			return [];
		} else {
			$count = count($testing);
			if ($count == 1) {
				return end($testing);
			} else {

				$nr = 1;
				echo "Ketten möglich.\n";
				foreach ($testing as $test) {
					$kette = Character::toString($test);
					$calcErrorPixel = $this->calcErrorPixel($test);
					echo "Kette ${nr}/${count}: '${kette}' => ${calcErrorPixel}  :: ";
					Character::toString($test, true);
					$nr++;
				}

				$nr = 1;
				foreach ($testing as $test) {
					$calcErrorPixel = $this->calcErrorPixel($test);
					if ($calcErrorPixel == 0) {
						echo "YAY\n";
						return $test;
					}
					$nr++;
				}
				return [];
			}
		}
	}

	/**
	 * @param Character[] $characters
	 * @param bool $complete
	 * @return integer
	 * @throws \Exception
	 */
	private function calcErrorPixel($characters, $complete = false)
	{
		$offsetX = 0;
		$this->maxX = -1;

		$toString = Character::toString($characters);
		if (empty($characters) || $toString == '') {
			return 0;
		}
		$length = Character::calcWidth($characters);
		if ($length <= 0) {
			throw new \Exception('Kürzer als leer darf eine Kette nicht sein!');
		}
		if ($length > $this->width + 1) {
			return $this->height;
		}
		$this->emptyComparisonImage();
		$somethingNotOfNew = false;
		$testForOverwritten = false;
		foreach ($characters as $index => $character) {
			// neues zeichen komplett ausserhalb des vergleichbereichs
			if ($offsetX >= $this->width) {
				return $this->height;
			}
			// Zeichen dem Vergleichsbild hinzufügen
			if ($character->character == '') {
				// weil Rückschritt den Vergleichsmode einschalten
				$testForOverwritten =  $index > 0;
			} else {
				$characterImage = $this->characters[$character->width][$character->character];
				for ($x = 0; $x < $character->width; $x++) {
					$setX = $x + $offsetX;
					if ($setX < 0 ) {
						continue;
					}
					if ($setX >= $this->width) {
						return $this->height;
					}
					$this->maxX = $setX;
					for ($y = 0; $y < $this->height; $y++) {
						$color = imagecolorat($characterImage, $x, $y);
						$current = imagecolorat($this->imageForComparison, $setX, $y);
						if (
							$testForOverwritten
							&&
							$current != $this->backgroundColor
							&&
							$color == $this->backgroundColor
						) {
							$somethingNotOfNew = true;
						}
						if ($color != $this->backgroundColor) {
							imagesetpixel($this->imageForComparison, $setX, $y, $color);
						}
					}
				}
				// das vorletze zeichen war ein rückschritt
				// war der überdeckte bereich was eigenständiges?
				if ($testForOverwritten && !$somethingNotOfNew) {
					return $this->height;
				}
			}
			$offsetX += $character->width;
		}
		$errors = 0;
		for ($y = 0; $y < $this->height; $y++) {
			for ($x = 0; $x <= $this->maxX; $x++) {
				$a = imagecolorat($this->imageForComparison, $x, $y);
				$b = imagecolorat($this->imageToParse, $x, $y);
				if ($a != $b) {
					$errors++;
				}
			}
		}
		return $errors;
	}

	/**
	 * parses a file for letters and returns the found string
	 * @param string $filename
	 * @return string
	 * @throws \Exception
	 */
	public function read($filename)
	{
		echo "Parse File $filename \n";
		$this->debug->echoString("Parse File $filename \n", 2);
		$parts = $this->splitImage(imagecreatefrompng($filename));
		$result = '';
		$collect = [];
		foreach ($parts as $index => $part) {
			$this->imageToParse = $part;
			$this->width = imagesx($this->imageToParse);
			$this->imageForComparison = imagecreatetruecolor($this->width, $this->height);
			$this->backgroundColor = imagecolorallocate($this->imageForComparison, 255, 255, 255);
			$this->emptyComparisonImage();
			$characters = $this->testCharacters([]);
			$ergebniss = Character::toString($characters);
//			echo "=> " . $ergebniss . " ";
//			Character::toString($characters, true);
			$result .= $ergebniss;
			$collect = array_merge($collect, $characters);
		}


		$ergebniss = Character::toString($collect);
		echo "=> " . $ergebniss . " ";
		Character::toString($collect, true);
		return $result;
	}

	/**
	 * @param resource $image
	 * @return resource[]
	 */
	private function splitImage($image)
	{
		$width = imagesx($image);
		$parts = [];
		$state = true;
		for ($x = 0, $startX = 0; $x < $width; $x++) {
			$isEmpty = $this->isEmpty($image, $x);
			if ($isEmpty != $state) {
				if ($state) {
					$startX = $x;
				} else {
					$partWidth = $x - $startX;
					$part = imagecreatetruecolor($partWidth, $this->height);
					imagecopy($part, $image, 0, 0, $startX, 0, $partWidth, $this->height);
					$parts[] = $part;
				}
				$state = $isEmpty;
			}
		}
		return $parts;
	}

	/**
	 * test if the current
	 * @param resource $image
	 * @param int $x
	 * @return bool
	 */
	private function isEmpty($image, $x)
	{
		$width = imagesx($image);
		if ($x < 0 || $x >= $width) {
			return true;
		}
		for ($y = 0; $y < $this->height; $y++) {
			$color = imagecolorat($image, $x, $y);
			if ($color != 0xFFFFFF) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return bool
	 */
	private function emptyComparisonImage()
	{
		return imagefilledrectangle($this->imageForComparison, 0, 0, $this->width - 1, $this->height - 1, $this->backgroundColor);
	}

//	private function test()
//	{
//		echo "HAHA!!\n\n";
//		$test = [
//			new Character('', -1),
//			new Character('j', 3),
//			new Character('', 1),
//			new Character('', 1),
//			new Character('a', 5),
//			new Character('', 1),
//			new Character('e', 4),
//			new Character('', 1),
//			new Character('', 1),
//			new Character('k', 5),
//			new Character('', 1),
//			new Character('e', 4),
//			new Character('', 1),
//			new Character('', 1),
//			new Character('l', 1),
//			new Character('', 1),
//			new Character('', 1),
////			new Character('l',1),
//			new Character('.', 1),
//			new Character('', 1),
//			new Character('', 1),
//		];
//		$this->calcErrorPixel($test);
//	}
}
