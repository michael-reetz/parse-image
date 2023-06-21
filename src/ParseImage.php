<?php
/**
 * @copyright 2018 Michael Reetz
 * @license   read /LICENSE
 * @link      http://www.reetzclan.de
 */

namespace MichaelReetz;

use Exception;
use LogicException;

/**
 * Written in a bit more than a night session because the yield of ocr was much too bad for
 * these small but very regular letters. Now it is perfect.
 */
class ParseImage
{

	/** @var resource[][]  [width][character] => image */
	private array $alphabet = [];

	/** @var int|null */
	private ?int $height = null;

	/** @var Debug */
	private Debug $debug;

	/** @var resource|null */
	private $imageToParse;

	/** @var resource|null */
	private $imageForComparison;

	/** @var int|null */
	private ?int $backgroundColor;

	/** @var int */
	private int $width = 0;

	/** @var string[] "md5 of Bitmask" -> string */
	private array $parsedImages = [];

	/**
	 * Reads letters to build letter knowledge
	 * ReadEmail constructor.
	 */
	public function __construct()
	{
		if (!file_exists(__DIR__ . '/characters') || !is_dir(__DIR__ . '/characters')) {
			throw new LogicException('You seam to missing the link to the character folder!');
		}
		$this->debug = Debug::getInstance();
		foreach (scandir(__DIR__ . '/characters') as $file) {
			if (!is_file(__DIR__ . '/characters/' . $file)) {
				continue;
			}
			$this->readCharacterFile($file);
		}
		krsort($this->alphabet);
		$this->debug->varDump($this->alphabet);
	}

	/**
	 * first character is the letter
	 * second might by "-" to tell that the first column of the image might be ignored
	 * optional "_" as second or third a.t.m. only because of windows because a === A for windows ; stupid :-/
	 * @param string $characterFilename
	 */
	private function readCharacterFile(string $characterFilename): void
	{
		$this->debug->echoString("read character file $characterFilename\n", 4);
		$char = $characterFilename[0];
		$image = imagecreatefrompng(__DIR__ . '/characters/' . $characterFilename);
		if ($this->height === null) {
			$this->height = imagesy($image);
		}
		$this->setupCharacter($image, $char);
	}

	/**
	 * @param resource $image
	 * @param string $character
	 */
	private function setupCharacter($image, string $character): void
	{
		$width = imagesx($image);
		$this->debug->echoString("add character $character with width $width\n", 4);
		if (!array_key_exists($width, $this->alphabet)) {
			$this->alphabet[$width] = [];
		}
		$this->alphabet[$width][$character] = $image;
		$md5 = $this->imageToMd5($image);
		$this->addToLookUpTable($character, $md5);
	}

	/**
	 * @param Character[] $current
	 * @param int $backTrack
	 * @return Character[][]
	 * @throws Exception
	 */
	private function buildTestList(array $current, int $backTrack): array
	{
		if ($backTrack > 0) {
			$current[] = new Character('', -$backTrack);
		}
		$testing = [];
		foreach ($this->alphabet as $testWidth => $letters) {
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
	 * @return int|false FALSE, wenn es keinen perfekten Treffer gab.
	 */
	private function checkTestList(array &$testList)
	{
		$errorCounts = array_map([$this, 'calcErrorPixel'], $testList);
		$perfect = array_filter(
			$errorCounts,
			static function($errorCount)
			{
				return $errorCount === true;
			}
		);
		if (!empty($perfect)) {
			if (count($perfect) > 1) {
				uksort(
					$perfect,
					static function ($index) use ($testList)
					{
						return count($testList[$index]);
					}
				);
			}
			reset($perfect);
			return key($perfect);
		}
		$testList = array_filter(
			$testList,
			static function ($key) use($errorCounts)
			{
				return $errorCounts[$key] <= 5;
			},
			ARRAY_FILTER_USE_KEY
		);
		return false;
	}

	/**
	 * @param Character[] $characters
	 * @param int $nested
	 * @return Character[]
	 * @throws Exception
	 */
	private function testCharacters(array $characters, int $nested = 0): array
	{
		$nested++;
		if ($nested > 80) {
			$this->debug->echoString("ARRRRGGGGG $nested 80 überschritten!\n", 1);
			exit;
		}

		$testing0 = $this->buildTestList($characters, 0);
		$hit = $this->checkTestList($testing0);
		if ($hit !== false) {
			return $testing0[$hit];
		}

		// z. B. "k<j v<w w<v <j w<w w<t"
		$testing1 = $this->buildTestList($characters, 1);
		$hit = $this->checkTestList($testing1);
		if ($hit !== false) {
			return $testing1[$hit];
		}

		// z.B. w<<j
		$testing2 = $this->buildTestList($characters, 2);
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
	 * Soll das neue Zeichen komplett ausserhalb des vergleichbereichs positioniert werden
	 * @param int $offsetX
	 * @return bool
	 */
	private function isNewCharacterOutsideOfRightBoundary(int $offsetX): bool
	{
		return $offsetX >= $this->width;
	}

	/**
	 * Zeichen dem Vergleichsbild hinzufügen, dabei ggf auch prüfen, dass das voerherige
	 * Bild eine Existenzberechtigung hatte
	 *
	 * @param int $offsetX
	 * @param Character $character
	 * @param bool $testForOverwritten
	 * @return int|false MaxX
	 */
	private function addCharacterToComparisonImage(int $offsetX, Character $character, bool $testForOverwritten)
	{
		$maxX = -1;
		$somethingNotOfNew = false;
		$characterImage = $this->alphabet[$character->width][$character->character];
		for ($x = 0; $x < $character->width; $x++) {
			$setX = $x + $offsetX;
			if ($setX < 0 ) {
				continue;
			}
			if ($setX >= $this->width) {
				return false;
			}
			$maxX = $setX;
			for ($y = 0; $y < $this->height; $y++) {
				$color = imagecolorat($characterImage, $x, $y);
				$current = imagecolorat($this->imageForComparison, $setX, $y);
				if (
					$testForOverwritten
					&&
					$current !== $this->backgroundColor
					&&
					$color === $this->backgroundColor
				) {
					$somethingNotOfNew = true;
				}
				if ($color !== $this->backgroundColor) {
					imagesetpixel($this->imageForComparison, $setX, $y, $color);
				}
			}
		}
		// das vorletze Zeichen war ein Rückschritt
		// war der überdeckte Bereich etwas eigenständiges?
		if ($testForOverwritten && !$somethingNotOfNew) {
			return false;
		}
		return $maxX;
	}

	/**
	 * Wenn der Rückschritt gleich groß ist wie das voriges Zeichen breit war,
	 * muss der Vergleichsmodus einschalten werden
	 *
	 * Das vordere Zeichen muss eine Existenzberechtigung haben.
	 * es darf nicht durch das nachfolgende komplett überdeckt werden
	 * z. B. l<k  oder .<l (diverse möglich)
	 *
	 * @param Character[] $characters
	 * @param int $index
	 * @return bool
	 */
	private function getDoWeNeedToDoTheTestForOverwritten(array $characters, int $index): bool
	{
		if ($index === 0) {
			return false;
		}
		if (abs($characters[$index]->width) === $characters[$index-1]->width) {
			return true;
		}
		return false;
	}

	/**
	 * @param Character[] $characters
	 * @return int|true Falls "true", dann ist das Bild zu 100 % erfüllt
	 * @throws Exception
	 */
	private function calcErrorPixel(array $characters)
	{
		if (empty($characters) || Character::toString($characters) === '') {
			return 0;
		}
		$length = Character::calcWidth($characters);
		if ($length <= 0) {
			throw new LogicException('Kürzer als leer darf eine Kette nicht sein!');
		}
		if ($length > $this->width + 1) {
			return $this->height;
		}
		$this->emptyComparisonImage();

		$maxX = -1;
		$offsetX = 0;
		$testForOverwritten = false;
		foreach ($characters as $index => $character) {
			if ($this->isNewCharacterOutsideOfRightBoundary($offsetX)) {
				return $this->height;
			}
			if ($character->character === '') {
				$testForOverwritten = $this->getDoWeNeedToDoTheTestForOverwritten($characters, $index);
			} else {
				$maxX = $this->addCharacterToComparisonImage($offsetX, $character, $testForOverwritten);
				if ($maxX === false) {
					return $this->height;
				}
				$testForOverwritten = false;
			}
			$offsetX += $character->width;
		}
		return $this->interpretMismatchPixelsInCurrentArea($maxX);
	}

	/**
	 * parses a file for letters and returns the found string
	 * @param string $filename
	 * @return string
	 * @throws Exception
	 */
	public function read(string $filename): string
	{
		$start = microtime(true);
		$this->debug->echoString("Parse File $filename \n", 3);
		$image = @imagecreatefrompng($filename);
		if ($image=== false) {
			$this->debug->echoString("This is not an image file!\n", 1);
			$parts = [];
		} else {
			$parts = $this->splitImage($image);
		}
		$result = '';
		foreach ($parts as $part) {
			$this->imageToParse = $part;
			$this->width = imagesx($this->imageToParse);
			$this->backgroundColor = imagecolorallocate($this->imageToParse, 255, 255, 255);
			$md5 = $this->imageToMd5();
			if (array_key_exists($md5, $this->parsedImages)) {
				$hit = $this->parsedImages[$md5];
			} else {
				$this->imageForComparison = imagecreatetruecolor($this->width, $this->height);
				$this->emptyComparisonImage();
				$characters = $this->testCharacters([]);
				$hit = Character::toString($characters);
				if ($hit === '') {
					$this->debug->echoString("################ TEILSEQUENZ NICHT GEFUNDEN ###############\n", 1);
				} else {
					$this->addToLookUpTable($hit, $md5);
				}
			}
			$result .= $hit;
		}
		$end = microtime(true);
		$length = strlen($result);

		if ($length > 0) {
			$timeDiff = max(0.001, $end - $start);
			if ($timeDiff <= 0.001) {
				$this->debug->echoString(' > ', 3);
			}
			$speed = round($length / ($timeDiff));
			$this->debug->echoString($speed . " char/sec\n", 3);
		} else {
			$this->debug->echoString("Nothing found!\n", 1);
		}
		return $result;
	}

	/**
	 * @param resource $image
	 * @return resource[]
	 */
	private function splitImage($image): array
	{
		$width = imagesx($image);
		$parts = [];
		$state = true;
		for ($x = 0, $startX = 0; $x < $width; $x++) {
			$isEmpty = $this->isEmpty($image, $x);
			if ($isEmpty !== $state) {
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
	private function isEmpty($image, int $x): bool
	{
		$width = imagesx($image);
		if ($x < 0 || $x >= $width) {
			return true;
		}
		for ($y = 0; $y < $this->height; $y++) {
			$color = imagecolorat($image, $x, $y);
			if ($color !== 0xFFFFFF) {
				return false;
			}
		}
		return true;
	}

	private function emptyComparisonImage(): void
	{
		imagefilledrectangle(
			$this->imageForComparison,
			0, 0,
			$this->width - 1, $this->height - 1,
			$this->backgroundColor
		);
	}

	/**
	 * @param resource $image
	 * @return string
	 */
	private function imageToMd5($image = null): string
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
				$b = $white !== imagecolorat($image, $x, $y);
				$byte |= $b;
				$cnt++;
				if ($cnt % 8 === 0) {
					$chr = chr($byte);
					$stream .= $chr;
					$byte = 0;
				}
				$byte <<= 1;
			}
		}
		return md5($stream);
	}

	/**
	 * @param string $string
	 * @param string $md5
	 */
	private function addToLookUpTable(string $string, string $md5): void
	{
		$this->debug->echoString("$md5 =:: $string\n", 2);
		$this->parsedImages[$md5] = $string;
	}

	/**
	 * Errechnet die Anzahl an Pixeln die nicht übereinstimmen. Falls das komplette
	 * Bild überprüft wurde, wird "true" zurückgegeben.
	 *
	 * @param int $maxX
	 * @return int|true true if done
	 */
	private function interpretMismatchPixelsInCurrentArea(int $maxX)
	{
		$errors = 0;
		for ($y = 0; $y < $this->height; $y++) {
			for ($x = 0; $x <= $maxX; $x++) {
				$a = imagecolorat($this->imageForComparison, $x, $y);
				$b = imagecolorat($this->imageToParse, $x, $y);
				if ($a !== $b) {
					$errors++;
				}
			}
		}
		if (($errors === 0) && ($maxX === ($this->width - 1))) {
			return true;
		}
		return $errors;
	}
}
