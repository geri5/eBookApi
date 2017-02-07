<?php
class router
{
    
    public static $routers = array('gen' => array(), 'reg' => array());
    
    public static function group($r, $type = 'gen', $middleWare = array()) {
        self::$routers[$type] = array_merge(self::$routers[$type], $r);
    }
    
    public static function add($rule, $c = 'run', $type = 'gen', $middleWare = array()) {
        self::$routers[$type][$rule] = $c;
    }
    
}