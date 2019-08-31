<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

define('TEMP_DIR', __DIR__ . '/temp');

ini_set('display_errors', 'On');
error_reporting(E_ALL);

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
$application->add(new \Higurashi\Command\InsertNames());
$application->add(new \Higurashi\Command\Volume());
$application->add(new \Higurashi\Command\DLLUpdate());
$application->add(new \Higurashi\Command\ListNames());
$application->add(new \Higurashi\Command\LipSync());
$application->add(new \Higurashi\Command\LipSyncLegacy());
$application->add(new \Higurashi\Command\Missing());
$application->add(new \Higurashi\Command\Voices());
$application->add(new \Higurashi\Command\VoicePack());
$application->add(new \Higurashi\Command\SpritePack());
$application->add(new \Higurashi\Command\RyukishiPack());
$application->add(new \Higurashi\Command\FullArcUpgrade());
$application->add(new \Higurashi\Command\FixPS2Voices());
$application->add(new \Higurashi\Command\DetectInterruptedVoices());
$application->add(new \Higurashi\Command\DetectMultilineVoices());
$application->add(new \Higurashi\Command\RemoveDisplayWindow());
$application->add(new \Higurashi\Command\UpgradeTranslation());

$application->run();
