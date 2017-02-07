<?php

class CFile
{

    const VERSION = '1.1.0';

    /**
     * download
     *
     * @param string $httpPath 文件网络地址
     * @param string $savePath 文件保存路径，例如 D:\1.jpg
     * @param boolean $autoType 是否自动识别文件类型，若为true，则文件保存目录不用带文件类型，例如 D:\1，将会自动补全类型，与原文件一致
     * @param boolean $gzip 是否开启GZIP
     * @param boolean $cover 若文件已存在是否覆盖
     * @return mixed
     * @author eric <1626023124@qq.com>
     * @created time :2016-12-31 12:26
     */
    public static function download($httpPath, $savePath, $autoType = false, $gzip = false, $cover = true)
    {
        $httpPath = trim($httpPath);
        $savePath = trim($savePath);
        if (empty($httpPath) || empty($savePath)) {
            return false;
        }
        if ($autoType) {
            $fType = pathinfo($httpPath, PATHINFO_EXTENSION);
            $savePath .= '.' . $fType;
        }
        if (!$cover && file_exists($savePath)) {
            // 如果不覆盖已存在文件且文件已存在，直接返回路径
            return $savePath;
        }
        $fileContent = self::get_file_content($httpPath, $gzip);
        if (empty($fileContent)) {
            // 这里有个坑，文件内容不能为0,false...
            return false;
        }
        if (self::_mkdir(pathinfo($savePath, PATHINFO_DIRNAME))) {
            if (file_put_contents($savePath, $fileContent, LOCK_EX) === false) {
                return false;
            } else {
                return $savePath;
            }
        } else {
            return false;
        }
    }

    public static function get_file_content($httpPath, $timeout = 10, $gzip = false)
    {
        $httpPath = trim($httpPath);
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $httpPath);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            if ($gzip) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Encoding:gzip'));
                curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
            }
            $content = curl_exec($ch);
            curl_close($ch);
        } else {
            $opts = array(
                'http' => array(
                    'method' => "GET",
                    'timeout' => $timeout,
                ),
            );
            $content = file_get_contents($httpPath, false, stream_context_create($opts));
        }
        return $content;
    }

    public static function _mkdir($dir)
    {
        $dir = trim($dir);
        if (is_dir($dir)) {
            return true;
        }
        if (mkdir($dir, 0777, true)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * _scandir
     *
     * @param string $rootDir 欲扫描的根目录，默认为当前目录
     * @param string $childDir 欲扫描的目录相对$rootDir的路径，若是扫描$rootDir该参数可不填或填/
     * @param array $ext 筛选文件后缀，只返回这些后缀的文件名，不填或传空数组则为全部返回，无后缀文件可用array('')
     * @return mixed 成功返回数组，失败返回false
     * @author eric <1626023124@qq.com>
     * @created time :2016-01-01 12:16
     */
    public static function _scandir($rootDir = './', $childDir = '', $ext = array())
    {
        if ($childDir != '' && $childDir{0} == '/') {
            $childDir = substr($childDir, 1);
        } // 去掉开头的/
        if (substr($rootDir, -1) != '/') {
            $rootDir .= '/';
        } // 防止结尾没有加/
        if ($childDir != '' && substr($childDir, -1) != '/') {
            $childDir .= '/';
        }
        $files = array();
        $dirFiles = scandir($rootDir . $childDir);
        if ($dirFiles === false) {
            return false;
        }
        array_splice($dirFiles, 0, 2); // 前两个是.和..
        foreach ($dirFiles as $name) {
            if (is_dir($rootDir . $childDir . $name)) {
                $cDirFiles = self::_scandir($rootDir, $childDir . $name . '/', $ext); // 递归取子目录下的文件
                if ($cDirFiles === false) {
                    return false; // 子目录扫描出错则返回false
                }
                $files = array_merge($files, $cDirFiles);
            } else {
                // 不是目录那就是文件了
                if (count($ext) == 0 || in_array(pathinfo($name, PATHINFO_EXTENSION), $ext)) {
                    // 不能用empty判断，要支持只筛选无后缀文件
                    $files[] = $childDir . $name; //相对$rootDir的路径
                }
            }
        }
        return $files;
    }

}