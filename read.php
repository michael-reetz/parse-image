<?php
/**
 * @copyright 2018 Michael Reetz
 * @license   read /LICENSE
 * @link      http://www.reetzclan.de
 */

require 'src/Singleton.php';
require 'src/Debug.php';
require 'src/ParseImage.php';
require 'src/Character.php';

\MichaelReetz\Debug::getInstance()->verbose()->verbose()->verbose();

$parseImage = new MichaelReetz\ParseImage();

if ($argc == 1) {
	$errors = [];
	foreach (scandir('input') as $file) {
		$filename = __DIR__ . '/input/' . $file;
		if (!is_file($filename)) {
			continue;
		}
		try {
//			echo "\nParse File $filename \n";
			echo $parseImage->read($filename) . "\n";
		} catch (Exception $exception) {
			$errors[] = $exception->getMessage();
		}
	}
	echo "\n" . implode("\n", $errors) . "\n";

} else {
	$filename = $argv[1];
	try {
		if (!file_exists($filename)) {
			throw new Exception('File not there');
		}
		echo $parseImage->read($filename) . "\n";
	} catch (Exception $exception) {
		echo $exception->getMessage();
	}
}
