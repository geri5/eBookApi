<?php
function autoload($classname)
{
    if (file_exists(__DIR__ . '/../library/' . $classname . '.php')) {
        require __DIR__ . '/../library/' . $classname . '.php';
    } else {
        require __DIR__ . '/' . $classname . '.php';
    }
}

function config($cstr)
{
    $c = explode('.', $cstr);
    $fir = true;
    foreach ($c as $v) {
        if ($fir) {
            $fir = false;
            $config = $GLOBALS['apiConfig'][$v];
        } else {
            $config = $config[$v];
        }
    }
    return $config;
}
