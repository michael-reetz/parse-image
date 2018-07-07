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

	private $massiveWrite = false;

	/**
	 * @param Character[] $characters
	 * @param int $nested
	 * @return Character[]
	 * @throws \Exception
	 */
	private function testCharacters($characters, $nested = 0)
	{

		$nested++;
//		if ($this->lastEcho + 0.2 < microtime(true)) {
//			$kette = Character::toString($characters);
//			echo "Aktuell: '${kette}'              \n";
//			$this->lastEcho = microtime(true);
//		}
		// berechne aktuellen score und falls zu schlecht durect return
		$errorPixels = $this->calcErrorPixel($characters);

		if (
			(
				count($characters) > 0 && $characters[0]->character == 'j'
				||
				count($characters) > 1 && $characters[1]->character == 'j'
			)
			&&
			$errorPixels < 14
		) {
			$this->massiveWrite = true;

			usleep(1000);
			$kette = Character::toString($characters, true);
			echo "Aktuell: '${kette}'  $errorPixels            \n";

		} else {
			$this->massiveWrite = false;
		}

		if (
			($errorPixels == 0)
			&&
			($this->maxX == ($this->width - 1))
		) {
			$errorPixels = $this->calcErrorPixel($characters);

			var_dump($errorPixels, $this->maxX, $this->width-1);

			$kette = Character::toString($characters, true);
			echo "DONE: '${kette}'  \n";
			return $characters;
		}


//

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
			echo "keine gültigen Ketten übrig\n";
			return [];
		} else {
			$count = count($testing);
			if ($count == 1) {
				echo "so soll es sein, Rückgabe einer eindeutigen Kette bis hier\n";
				return end($testing);
			} else {
				echo "mehrere Ketten möglich\n";
				$nr = 1;
				foreach ($testing as $test) {
					$kette = Character::toString($test, true);
					echo "breche ab bei Kette ${nr}/${count}: '${kette}'\n";
					$calcErrorPixel = $this->calcErrorPixel($test);
					echo "\tERRORLEVEL : " . $calcErrorPixel . "\n";
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
		if (empty($characters) || Character::toString($characters) == '') {
			if ($this->massiveWrite) echo "AUA";
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
		$offsetX = 0;
		$this->maxX = 0;
		foreach ($characters as $character) {
			// neues zeichen komplett ausserhalb des vergleichbereichs
			if ($offsetX >= $this->width) {
				return $this->height;
			}
			// Zeichen dem Vergleichsbild hinzufügen
			if ($character->character != '') {
				$characterImage = $this->characters[$character->width][$character->character];
				for ($x = 0; $x < $character->width; $x++) {
					$setX = $x + $offsetX;
					if ($setX < 0 || $setX >= $this->width) {
						continue;
					}
					$this->maxX = $setX;
					for ($y = 0; $y < $this->height; $y++) {
						$color = imagecolorat($characterImage, $x, $y);
						if ($color != $this->backgroundColor) {
							imagesetpixel($this->imageForComparison, $setX, $y, $color);
						}
					}
				}
			}
			$offsetX += $character->width;
		}
		$errors = 0;
		for ($x = 0; $x < $this->maxX; $x++) {
			for ($y = 0; $y < $this->height; $y++) {
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
		foreach ($parts as $index => $part) {
			$this->imageToParse = $part;
			$this->width = imagesx($this->imageToParse);
			$this->imageForComparison = imagecreatetruecolor($this->width, $this->height);
			$this->backgroundColor = imagecolorallocate($this->imageForComparison, 255, 255, 255);
			$this->emptyComparisonImage();
			$characters = $this->testCharacters([]);
			echo "\nHMMMMM" . Character::toString($characters, true) . "\n";
			exit;
		}
		exit;
//		return Character::toString($characters);
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
			echo $isEmpty ? ' ' : '#';
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
