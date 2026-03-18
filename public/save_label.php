<?php
require_once __DIR__ . '/../common.php';

// 1. 校验请求方式
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    saveLog('请求方式错误:必须是POST,实际:' . $_SERVER['REQUEST_METHOD']);
    exit('{"code":-1,"msg":"请求方式错误"}');
}

// 2. 校验参数
$videoId = isset($_POST['videoId']) ? (int)$_POST['videoId'] : 0;
$result = isset($_POST['result']) ? (int)$_POST['result'] : 0;

if ($videoId <= 0) {
    saveLog('参数错误: videoId无效');
    exit('{"code":-2,"msg":"参数错误: videoId无效"}');
}

// 3. 校验结果值 (1=正常, 2=垃圾)
if (!in_array($result, [1, 2])) {
    saveLog('参数错误: result无效，必须为1或2');
    exit('{"code":-3,"msg":"参数错误: 结果值无效"}');
}

// 4. 写入数据库
try {
    setVideoResult($videoId, $result);
    saveLog("人工标注成功: videoId={$videoId}, result={$result}");
    echo '{"code":0,"msg":"OK"}';
} catch (Exception $e) {
    saveLog("人工标注异常: " . $e->getMessage());
    echo '{"code":-4,"msg":"数据库写入失败"}';
}
