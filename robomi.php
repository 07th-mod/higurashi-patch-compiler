<?php

use Dibi\Connection;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/robomi.php';

define('TEMP_DIR', __DIR__ . '/temp');

ini_set('display_errors', 'On');
error_reporting(E_ALL);


$config = Yaml::parse(file_get_contents(__DIR__ . '/config/local.yml'));

$options = $config['database'];
$options['driver'] = 'pdo';
$options['charset'] = 'utf8';
$connection = new Connection($options);

dibi::setConnection($connection);

$files = \Nette\Utils\Finder::findFiles('*.txt')
    ->in($_SERVER['argv'][1]);

foreach ($files as $file) {
    processFile($file->getPathname());
}
