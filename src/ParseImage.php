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
		$md5 = $this->imageToMd5($image);
		$this->addToLookUpTable($character, $md5);
	}

	/**
	 * @param Character[] $current
	 * @param int $backTrack
	 * @return Character[][]
	 * @throws \Exception
	 */
	private function buildTestList($current, $backTrack = 0)
	{
		if ($backTrack > 0) {
			$current[] = new Character('', -$backTrack);
		}
		$testing = [];
		foreach ($this->characters as $testWidth => $letters) {
			foreach ($letters as $char => $testImage) {
				if ($testWidth <= $backTrack) {
					continue;
				}
				$character = new Character($char, $testWidth);
				$test = $current;
				$test[] = $character;
				$testing[] = $test;
			}
		}
		return $testing;
	}

	/**
	 * @param Character[][] $testList
	 * @return int|false FALSE wenn es keinen perfekten Treffer gab
	 */
	private function checkTestList(&$testList)
	{
		$errorCounts = array_map([$this, 'calcErrorPixel'], $testList);
		$perfect = array_filter(
			$errorCounts,
			function($errorCount){return $errorCount === true;}
		);
		if (!empty($perfect)) {
			if (count($perfect) > 1) {
				uksort(
					$perfect,
					function ($index) use ($testList) { return count($testList[$index]); }
				);
			}
			reset($perfect);
			return key($perfect);
		}
		$testList = array_filter(
			$testList,
			function ($key) use($errorCounts) { return $errorCounts[$key] <= 5; },
			ARRAY_FILTER_USE_KEY
		);
		return false;
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
		if ($nested > 80) {
			echo "ARRRRGGGGG $nested 80 überschritten!";
			exit;
		}

		$testing0 = $this->buildTestList($characters,0);
		$hit = $this->checkTestList($testing0);
		if ($hit !== false) {
			return $testing0[$hit];
		}

		// z. B. "k<j v<w w<v <j w<w w<t"
		$testing1 = $this->buildTestList($characters,1);
		$hit = $this->checkTestList($testing1);
		if ($hit !== false) {
			return $testing1[$hit];
		}

		// z.B. w<<j
		$testing2 = $this->buildTestList($characters,2);
		$hit = $this->checkTestList($testing2);
		if ($hit !== false) {
			return $testing2[$hit];
		}

		$testing = array_merge($testing0, $testing1 );  // $testing2
		// ein array das einfach nur die gleichen keys wie $testing hat, aber ver wert von nested enthält
		$nestedList = array_combine(array_keys($testing), array_fill(0, count($testing), $nested));
		$testing = array_map(
			[$this, 'testCharacters'],
			$testing,
			$nestedList
		);
		$hit = $this->checkTestList($testing);
		if ($hit !== false) {
			return $testing[$hit];
		}
		return [];
	}

	/**
	 * @param Character[] $characters
	 * @return integer|true falls true dann ist das bild zu 100% erfüllt
	 * @throws \Exception
	 */
	private function calcErrorPixel($characters)
	{
		$offsetX = 0;
		$maxX = -1;

		if (empty($characters) || Character::toString($characters) === '') {
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
			if ($character->character === '') {
				if ($index > 0) {
					if (abs($character->width) == $characters[$index-1]->width) {
						// weil Rückschritt gleich groß wie voriges Zeichen den Vergleichsmode einschalten
						// soll heissen: Das vordere Zeichen muss eine Existenzberechtigung haben.
						// es darf nicht durch das nachfolgende komplett überschrieben werden
						// z. B. l<k
						$testForOverwritten = true;
					}
				}
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
					$maxX = $setX;
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
				$testForOverwritten = false;
			}
			$offsetX += $character->width;
		}
		$errors = 0;
		for ($y = 0; $y < $this->height; $y++) {
			for ($x = 0; $x <= $maxX; $x++) {
				$a = imagecolorat($this->imageForComparison, $x, $y);
				$b = imagecolorat($this->imageToParse, $x, $y);
				if ($a != $b) {
					$errors++;
				}
			}
		}
		if (
			($errors == 0)
			&&
			($maxX == ($this->width - 1))
		){
			return true;
		}
		return $errors;
	}

	private $parsedImages = [];

	/**
	 * parses a file for letters and returns the found string
	 * @param string $filename
	 * @return string
	 * @throws \Exception
	 */
	public function read($filename)
	{
		$start = microtime(true);
		$this->debug->echoString("Parse File $filename \n", 2);
		$parts = $this->splitImage(imagecreatefrompng($filename));
		$result = '';
		foreach ($parts as $index => $part) {
			$this->imageToParse = $part;
			$this->width = imagesx($this->imageToParse);
			$this->backgroundColor = imagecolorallocate($this->imageToParse, 255, 255, 255);
			$md5 = $this->imageToMd5();
			if (array_key_exists($md5, $this->parsedImages)) {
				$ergebniss = $this->parsedImages[$md5];
			} else {
				$this->imageForComparison = imagecreatetruecolor($this->width, $this->height);
				$this->emptyComparisonImage();
				$characters = $this->testCharacters([]);
				$ergebniss = Character::toString($characters);
				if ($ergebniss===''){
					echo "################ TEILSEQUENZ NICHT GEFUNDEN ###############\n";
				} else {
					$this->addToLookUpTable($ergebniss, $md5);
				}
			}
			$result .= $ergebniss;
		}
		$end = microtime(true);
		$length = strlen($result);
		if ($length > 0) {
			echo round($length / ($end-$start)) . " char/sec\n";
		}else {
			echo "Nothing found!\n";
		}
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

	/**
	 * @param resource $image
	 * @return string
	 */
	private function imageToMd5($image = null)
	{
		if (is_null($image)) {
			$image = $this->imageToParse;
		}
		$height = imagesy($image);
		$width = imagesx($image);
		$white = imagecolorallocate($image, 255, 255, 255);

		$byte = 0;
		$stream = '';
		$cnt = 0;
		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				$b = $white != imagecolorat($image, $x, $y);
				$byte = $byte | $b;
				$cnt++;
				if ($cnt % 8 === 0) {
					$chr = chr($byte);
					$stream .= $chr;
					$byte = 0;
				}
				$byte = $byte << 1;
			}
		}
		$md5 = md5($stream);
		return $md5;
	}

	/**
	 * @param string $string
	 * @param string $md5
	 */
	private function addToLookUpTable($string, $md5)
	{
		echo "$md5 =:: $string\n";
		$this->parsedImages[$md5] = $string;
	}
}
