#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use gazedb\tools\ClassGeneratorCommand;
use Symfony\Component\Console\Application;

$application = new Application('gazedb data structure helper', '1.0');
$application->add(new ClassGeneratorCommand());
$application->run();
