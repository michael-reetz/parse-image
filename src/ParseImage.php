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
	private $image = null;

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
//		if (substr($characterFilename, 1, 1) == '-') {
//			$width = imagesx($image);
//			$crop = ['x' => 1, 'y' => 0, 'width' => $width - 1, 'height' => $this->height];
//			$this->setupCharacter(imagecrop($image, $crop), $char);
//		}
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
	 * @return Character[]
	 */
	private function testCharacters($characters)
	{
		// berechne aktuellen score und falls zu schlecht durect return
		$errorPixels = $this->calcErrorPixel($characters);
		if ($errorPixels > 10) {
			$kette = Character::toString($characters);
			echo "Anzahl Fehler: ${errorPixels}, breche ab bei Kette '${kette}'\n";
			return [];
		}

		$length = Character::calcWidth($characters);
		if ($this->isEmpty($this->image, $length)) {
			$characters[] = new Character('',1);
			return $this->testCharacters($characters);
		} else {

			$testing = [];
			// aber nicht falls der letzte Character bereits ein rückschritt war
			if (empty($characters) || end($characters)->character != '') {
				// wenn ($length -1) not isEmpty dann auch -1 testen und wenn ($length -2) not isEmpty dann auch -2 testen
				if (!$this->isEmpty($this->image, $length-1)) {
					$test = $characters;
					$test[] = new Character('',-1);
					$testing[] = $this->testCharacters($test);
				}
//				if (!$this->isEmpty($this->image, $length-2)) {
//					$test = $characters;
//					$test[] = new Character('',-2);
//					$testing[] = $this->testCharacters($test);
//				}
			}
			// testen jedes Buchstaben
			foreach ($this->characters as $testWidth => $letters) {
				foreach ($letters as $char => $testImage) {

					if (
						!empty($characters)
						&&
						end($characters)->width < 0
						&&
						$testWidth <= abs(end($characters)->width)
					) {
						// zeichen ignorieren da durch backstep und zeichenbreite
						// kein weiterkommen stattfindet
						continue;
					}

					$test = $characters;
					$test[] = new Character($char, $testWidth);
					$testing[] = $this->testCharacters($test);
				}
			}
			// alle verworfenen Ketten rauswerfen
			$testing = array_filter($testing, function($a){return !empty($a);});
			// dieses Array dürfe nur noch länge 1 haben????
			if (empty($testing)){
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
					foreach($testing as $test) {
						$kette = Character::toString($test);
						echo "breche ab bei Kette ${nr}/${count}: '${kette}'\n";
					}
					return [];
				}
			}
		}
	}

	// hier weiter : an einem positiven space MUSS errorcout 0 sein

	// analyse kann dann nur ab dem letztem positiven Space beginnen

	/**
	 * @param Character[] $characters
	 * @return integer
	 */
	private function calcErrorPixel($characters)
	{
		echo "teste : " . Character::toString($characters).PHP_EOL;


		if (empty($characters) || Character::toString($characters) == '') {
			return 0;
		}

		if ($this->countValidatedCharacters > 0 ) {
			$skipX = Character::calcWidth(
				array_slice($characters,0, $this->countValidatedCharacters)
			);
		} else {
			$skipX = 0;
		}


		// TODO Optimiere, das nicht für jeden Vergleich ein neues Bild angelegt werden muss
		// TODO sondern am besten gleich zu anfang ein identisch großes zum parse Bild anlegen
		// TODO welches immer wieder hier verwendet wird
		$length = Character::calcWidth($characters);

//		var_dump($length, $this->height);
		if ($length <= 0 ){
			exit;
		}


		$img = imagecreatetruecolor($length, $this->height);
		$bg = imagecolorallocate ( $img, 255, 255, 255 );
		imagefilledrectangle($img,0,0,$length - 1, $this->height - 1, $bg);



		$offset = 0;

		foreach ($characters as $index => $character) {
			// nur den Vergleichteil malen
			if ($index >= $this->countValidatedCharacters) {
				if ($character->character != '') {
					$characterImage = $this->characters[$character->width][$character->character];
					for ($x = 0; $x < $character->width; $x++) {
						for ($y = 0; $y < $this->height; $y++) {
							$setX = $x + $offset;
							if ($setX < 0 || $setX >= $length) {
								continue;
							}
							$color = imagecolorat($characterImage, $x, $y);
							imagesetpixel($img, $setX, $y, $color);
						}
					}
				}
			}
			$offset += $character->width;
		}
		//imagepng($img, './testausgabe.png');
		$errors = 0;
		for($x = 0; $x < $length; $x++) {
			if ($x >= $this->width){
				continue ;
			}
			if ($x < $skipX) {
				continue;
			}
			for($y = 0; $y < $this->height; $y++) {
				$a = imagecolorat($img, $x, $y);
				$b = imagecolorat($this->image, $x, $y);
				if ($a != $b) {
					$errors++;
				}
				$setX = $x + $offset;
				if ($setX < 0 || $setX >= $length) {
					continue;
				}
				$color = imagecolorat($characterImage, $x, $y);
				imagesetpixel($img, $setX , $y, $color);
			}
		}

		$lastCharacter = end($characters);

		if ($lastCharacter->character == '' && $lastCharacter->width > 0){
			if ($errors != 0) {
				return 1000;
			}
			$this->countValidatedCharacters = count($characters);
			return 0;
		}

		return $errors;
	}

	private function test(){
		echo "HAHA!!\n\n";
		$test = [
			new Character('',-1),
			new Character('j',3),
			new Character('',1),
			new Character('',1),
			new Character('a',5),
			new Character('',1),
			new Character('e',4),
			new Character('',1),
			new Character('',1),
			new Character('k',5),
			new Character('',1),
			new Character('e',4),
			new Character('',1),
			new Character('',1),
			new Character('l',1),
			new Character('',1),
			new Character('',1),
//			new Character('l',1),
			new Character('.',1),
			new Character('',1),
			new Character('',1),
		];
		$this->calcErrorPixel($test);
	}

	/**
	 * @var integer Zeigt auf den letzten positiven space
	 */
	private $countValidatedCharacters;

	/**
	 * parses a file for letters and returns the found string
	 * @param string $filename
	 * @return string
	 * @throws \Exception
	 */
	public function read($filename)
	{
		$this->debug->echoString("Parse File $filename \n", 2);
		$this->image = imagecreatefrompng($filename);
		$this->width = imagesx($this->image);
		//$this->>test();

		$this->countValidatedCharacters = 0;

		/** @var Character[] $characters */
		$characters = $this->testCharacters([new Character('', -1)]);
		echo Character::toString($characters);
		exit;
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
		for ($y=0; $y<$this->height; $y++) {
			if (imagecolorat($image, $x, $y) != 0xFFFFFF) {
				return false;
			}
		}
		return true;
	}
}






