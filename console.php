<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

define('TEMP_DIR', __DIR__ . '/temp');

$application = new Application();

// Package compilation
$application->add(new \Higurashi\Command\Download());
$application->add(new \Higurashi\Command\Unpack());
$application->add(new \Higurashi\Command\Clean());
$application->add(new \Higurashi\Command\Compress());
$application->add(new \Higurashi\Command\Compare());
$application->add(new \Higurashi\Command\Make());

// Higurashi chapters
$application->add(new \Higurashi\Command\Compile\Onikakushi());
$application->add(new \Higurashi\Command\Compile\Watanagashi());
$application->add(new \Higurashi\Command\Compile\Tatarigoroshi());
$application->add(new \Higurashi\Command\Compile\Himatsubushi());
$application->add(new \Higurashi\Command\Compile\Meakashi());

// PS3 scripts helpers
$application->add(new \Higurashi\Command\ParseScript());

$application->run();
