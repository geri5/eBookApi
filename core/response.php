<?php
class response
{
    
    public static function make($msg, $errorCode = 0, $header = true)
    {
        if ($header) header('Content-Type:application/json;charset=utf8');
        if (!is_array($msg)) $msg = array('message' => $msg);
        if ($errorCode == 0) {
            $return['result'] = $msg;
        } else {
            $return = $msg;
        }
        $return['error'] = $errorCode;
        echo json_encode($return, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function sysError($header = true)
    {
        self::make('系统错误', 101, $header);
    }
    
}