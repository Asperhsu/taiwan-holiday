<?php

use Illuminate\Support\Carbon;
use AsperHsu\TaiwanHoliday\Crawler;

require __DIR__ . '/vendor/autoload.php';

if (!isset($argv[1])) {
    die('need year argument');
}

if (!Carbon::canBeCreatedFromFormat($year = $argv[1], 'Y')) {
    die('invalid year');
}

(new Crawler)->handle($year);
