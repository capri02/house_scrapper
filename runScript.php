<?php

set_time_limit(0);
require_once './Scrapper.php';

$className = $argv[1];
require_once "{$className}.php";
$site = new $className();

echo "{$className} \n";

while(true) {
    if ($site->checkDateAndTime())
        $site->runScript();
}
