<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

define('TEMP_DIR', __DIR__ . '/temp');

$application = new Application();

$application->add(new \Higurashi\Command\Download());
$application->add(new \Higurashi\Command\Unpack());

$application->add(new \Higurashi\Command\Tatarigoroshi());

$application->run();
