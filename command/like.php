<?php

// 项目根目录：
$root = __DIR__ . '/..';

// 图片文件夹名称
$mdImgDir = 'images';

// Markdown 图片文件夹
$mdImgDir = $root . '/' . $mdImgDir;

// 视频信息目录
$videosDir = $root . '/NaiveBayes/predicted_normal';

// 开始主流程

// 初始化 Markdown 图片文件夹
if (!is_dir($mdImgDir)) {
    mkdir($mdImgDir);
} else {
    // 删除后创建
    $files = scandir($mdImgDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            unlink($mdImgDir . '/' . $file);
        }
    }
    rmdir($mdImgDir);
    mkdir($mdImgDir);
}

// 读取视频信息目录下所有.json文件遍历处理生成 Markdown 文本
$markdownText = "# Bilibili推荐的视频\n\n";
function readVideosJsonFilesConfigs(string $dir): array {
    $files = scandir($dir);
    $configs = [];
    foreach ($files as $file) {
        if (is_file($dir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) == 'json') {
            $config = json_decode(file_get_contents($dir . '/' . $file), true);
            if (is_array($config)) {
                $configs[] = $config;
            }
        }
    }
    return $configs;
}
foreach (readVideosJsonFilesConfigs($videosDir) as $config) {
    $markdownText .= "## [{$config['title']}]({$config['videoUrl']})\n\n";
    $imageFileName = pathinfo($config['imagePath'], PATHINFO_BASENAME);
    // 拷贝文件到markdown图像目录
    copy($config['imagePath'], $mdImgDir . '/' . $imageFileName);
    $markdownText .= "![{$config['title']}]($mdImgDir/{$imageFileName})\n\n";
    if (!empty($config['description'])) {
        $markdownText .= "```\n";
        $markdownText .= "{$config['description']}\n";
        $markdownText .= "```\n\n";
    }
    $markdownText .= "UP： [{$config['authorName']}]({$config['authorPageUrl']})";
    if (empty($config['authorDescription'])) {
        $markdownText .= "\n\n";
    } else {
        $markdownText .= "，简介：\n\n";
        $markdownText .= "```\n";
        $markdownText .= "{$config['authorDescription']}\n";
        $markdownText .= "```\n\n";
    }
}

file_put_contents("$root/README.md", $markdownText);
