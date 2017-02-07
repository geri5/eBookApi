<?php
class framework
{
    
    public static function load($v, $params = array()) {
        if (is_array($v)) {
            require __DIR__ . '/../api/' . $v[0] . '.php';
            $c = new $v[0];
            $func = array($c, $v[1]);
        } elseif (is_object($v)) {
            $func = $v;
        } else {
            require __DIR__ . '/../api/' . $v . '.php';
            $c = new $v;
            $func = array($c, 'run');
        }
        if (!empty($params)) {
            unset($params[0]);
        }
        $result = call_user_func_array($func, $params);
        if (!empty($result)) {
            response::make($result);
        }
    }
    
    public static function init()
    {
        $api = isset($_GET['api']) ? $_GET['api'] : 'index';
        define('PATH_DATA', __DIR__ . '/../data');
        require __DIR__ . '/../config/inc_config.php';
        require 'function.php';
        spl_autoload_register('autoload');
        require __DIR__ . '/../config/routers.php';
        $routers = router::$routers;
        if (isset($routers['gen'][$api])) {
            self::load($routers['gen'][$api]);
        } else {
            foreach ($routers['reg'] as $rule => $v) {
                if (preg_match($rule, $api, $params)) {
                    self::load($v, $params);
                    exit;
                }
            }
            response::make('此API不存在', 102);
        }
    }
    
}
