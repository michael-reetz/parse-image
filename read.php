<?php
/**
 * Created by PhpStorm.
 * User: Michael Reetz
 * Date: 21.06.2018
 * Time: 19:43
 */

require 'src/Singleton.php';
require 'src/Debug.php';
require 'src/ParseImage.php';
require 'src/Candidate.php';
require 'src/Character.php';

\MichaelReetz\Debug::getInstance()/*->verbose()->verbose()*/;

$parseImage = new MichaelReetz\ParseImage();

if ($argc == 1) {
	$errors = [];
	foreach (scandir('input') as $file) {
		$filename = __DIR__ . '/input/' . $file;
		if (!is_file($filename)) {
			continue;
		}
		try {
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
