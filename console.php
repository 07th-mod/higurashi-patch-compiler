<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

define('TEMP_DIR', __DIR__ . '/temp');

$application = new Application();

// Chapter compilation
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

// Adventure Mode
$application->add(new \Higurashi\Command\ParseScript());
$application->add(new \Higurashi\Command\Names());
$application->add(new \Higurashi\Command\Adventure());
$application->add(new \Higurashi\Command\Colors());

// Console arcs
$application->add(new \Higurashi\Command\Normalize());

// Utilities
$application->add(new \Higurashi\Command\Combine());
$application->add(new \Higurashi\Command\Recompile());
$application->add(new \Higurashi\Command\CutVoices());
$application->add(new \Higurashi\Command\Placeholders());

$application->run();
