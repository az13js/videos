<?php
require_once __DIR__ . '/../common.php';

// 校验请求方式必须是POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    saveLog('请求方式错误:必须是POST,实际:' . $_SERVER['REQUEST_METHOD']);
    exit('{"code":-1,"msg":"请求方式错误"}');
}

// 校验请求方法必须POST一个JSON
$json = json_decode(file_get_contents('php://input'), true);
if (!is_array($json)) {
    saveLog('请求数据格式错误:必须是JSON,错误信息:' . json_last_error_msg());
    exit('{"code":-2,"msg":"请求数据格式错误"}');
}

/*
$json数据格式（示例）：
{
    "videos": [
        {
            "title": "游戏科学《黑神话：钟馗》全新 6 分钟实机",
            "videoUrl": "https:\/\/www.bilibili.com\/video\/BV1S7FUzNEiZ",
            "imageUrl": "https:\/\/i0.hdslb.com\/bfs\/archive\/d98bfd08e52dc6198b4f05f171ac4e489f5774a0.jpg@672w_378h_1c_!web-home-common-cover",
            "imageBase64": "data:image\/png;base64,iVBO...省略...",
            "description": "过了小年就是年！万众期待的游戏科学新作《黑神话：钟馗》6 分钟实机小短片为马年春节倾情献上。\n\n本视频是为马年春节专门录制，与游戏实际剧情无关，切勿轻信。\n\n《黑神话：钟馗》官网：gamesci.cn\/zhongkui",
            "authorName": "英伟达GeForce",
            "authorDescription": "1999年，NVIDIA （英伟达）发明了 GPU。",
            "authorPageUrl": "https:\/\/space.bilibili.com\/485703766"
        },
        {
            "title": "难绷莉爱玩怀旧游戏大鱼吃小鱼，想实现小时候的梦，但……",
            "videoUrl": "https:\/\/www.bilibili.com\/video\/BV1hywFzgESq",
            "imageUrl": "https:\/\/i0.hdslb.com\/bfs\/archive\/c51fd4ead8fa556b4f0470a843da0e2d635695a4.jpg@672w_378h_1c_!web-home-common-cover",
            "imageBase64": "data:image\/png;base64,iVBO...省略...",
            "description": "视频中的主播：@真红莉爱Official   \n关注莉爱吧\n直播时间：3月11号",
            "authorName": "-空咻-",
            "authorDescription": "加油鸭~加油鸭~向前冲鸭~",
            "authorPageUrl": "https:\/\/space.bilibili.com\/411196259"
        }
    ],
    "batchSize": 2,
    "collectedAt": "2026-03-15T09:33:10.504Z"
}
*/

// 校验格式
if (!array_key_exists('videos', $json) || !is_array($json['videos'])) {
    saveLog('请求数据格式错误:缺少videos字段');
    exit('{"code":-3,"msg":"请求数据格式错误"}');
}
foreach ($json['videos'] as $video) {
    if (!array_key_exists('title', $video) || !is_string($video['title'])) {
        saveLog('请求数据格式错误:缺少title字段');
        exit('{"code":-4,"msg":"请求数据格式错误"}');
    }
    if (!array_key_exists('videoUrl', $video) || !is_string($video['videoUrl'])) {
        saveLog('请求数据格式错误:缺少videoUrl字段');
        exit('{"code":-5,"msg":"请求数据格式错误"}');
    }
    if (!array_key_exists('imageUrl', $video) || !is_string($video['imageUrl'])) {
        saveLog('请求数据格式错误:缺少imageUrl字段');
        exit('{"code":-6,"msg":"请求数据格式错误"}');
    }
    if (!array_key_exists('imageBase64', $video) || !is_string($video['imageBase64'])) {
        saveLog('请求数据格式错误:缺少imageBase64字段');
        exit('{"code":-7,"msg":"请求数据格式错误"}');
    }
    // 检查Base64图片中的格式，是否为PNG格式
    if (strpos($video['imageBase64'], 'data:image/png;base64,') !== 0) {
        saveLog('图片格式错误:必须是PNG格式');
        exit('{"code":-8,"msg":"图片格式错误"}');
    }
    if (!array_key_exists('description', $video) || !is_string($video['description'])) {
        saveLog('请求数据格式错误:缺少description字段');
        exit('{"code":-9,"msg":"请求数据格式错误"}');
    }
    if (!array_key_exists('authorName', $video) || !is_string($video['authorName'])) {
        saveLog('请求数据格式错误:缺少authorName字段');
        exit('{"code":-10,"msg":"请求数据格式错误"}');
    }
    if (!array_key_exists('authorDescription', $video) || !is_string($video['authorDescription'])) {
        saveLog('请求数据格式错误:缺少authorDescription字段');
        exit('{"code":-11,"msg":"请求数据格式错误"}');
    }
    if (!array_key_exists('authorPageUrl', $video) || !is_string($video['authorPageUrl'])) {
        saveLog('请求数据格式错误:缺少authorPageUrl字段');
        exit('{"code":-12,"msg":"请求数据格式错误"}');
    }
}

if (!is_dir(IMAGE_SAVE_DIR)) {
    saveLog('图片保存目录不存在: ' . IMAGE_SAVE_DIR . ' 自动创建');
    mkdir(IMAGE_SAVE_DIR);
}

foreach ($json['videos'] as $video) {
    saveVideo($video);
}

saveLog('保存成功, 记录数：' . count($json['videos']));
echo '{"code":0,"msg":"OK","data":{"count":'.count($json['videos']).'}}';
