<?php
$scritpFile = 'runScript.php';
$exclusions = array('Scrapper.php', $scritpFile);
$currentPath = dirname( realpath( __FILE__ ) ) . DIRECTORY_SEPARATOR;
$handle = opendir('./');
if ($handle) {
    while (false !== ($entry = readdir($handle))) {
        $extension = substr(strrchr($entry, '.'), 1);
        if ($extension == 'php' && !in_array($entry, $exclusions)) {
            require_once $entry;
            $className = ucfirst(substr($entry, 0,strrpos($entry,'.')));
            if (class_exists($className)) {
                $commands[] = "start cmd /c php -f " . $currentPath . $scritpFile . " {$className}";
            }
        }
    }
    closedir($handle);
}