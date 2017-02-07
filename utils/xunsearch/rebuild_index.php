<?php
if (PHP_SAPI != 'cli') {
    exit("You must run the CLI environment\n");
}
ini_set('max_execution_time', 0);
set_time_limit(0);
if (intval(ini_get("memory_limit")) < 1024) {
    ini_set('memory_limit', '1024M');
}
require __DIR__ . '/../../config/inc_config.php';
require __DIR__ . '/../../core/function.php';
spl_autoload_register('autoload');
log::$log_show = log::$log_write = true;
log::$log_file = __DIR__ . '/rebuild_index.log';
try {
    $startTime = microtime(true);
    $xs = new XS(__DIR__ . '/../../config/' . config('searchByXS.ini'));
    $index = $xs->index;
    log::_echo("开始重建索引 ...\n");
    $index->beginRebuild();
    $fid = $xs->getFieldId();
    log::_echo("初始化数据源 ... \n");
    $datas = db::get_all("select * from `enovel_books`");
    $total = $total_ok = $total_failed = 0;
    log::_echo("开始批量导入数据 ...\n");
    $index->setTimeout(0);
    $index->openBuffer();
    foreach ($datas as $data) {
        $doc = new XSDocument('UTF-8');
        $pk = $data[$fid->name];
        $doc->setFields($data);
        try {
            $total++;
            $index->update($doc);
            $total_ok++;
        } catch (XSException $e) {
            log::warn("警告：添加第 {$total} 条数据失败 - " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $total_failed++;
        }
        if (($total % 10000) == 0) {
            log::_echo("报告：累计已处理数据 {$total} 条 ...\n");
        }
    }
    $index->closeBuffer();
    $index->endRebuild();
    $execTime = round(microtime(true) - $startTime, 2);
    log::info("完成索引重建：成功 {$total_ok} 条，失败 {$total_failed} 条，耗时 {$execTime} 秒");
} catch (XSException $e) {
    log::warn($e->getMessage() . "\n" . $e->getTraceAsString());
}
