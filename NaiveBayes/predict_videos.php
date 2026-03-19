<?php
/**
 * 推断脚本：加载模型，对未标注视频进行分类
 * 输出：目录下的 predicted_normal / predicted_spam 文件夹
 */

declare(strict_types=1);

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/helper.php';

function loadModelParams(string $path): array
{
    if (!file_exists($path)) {
        throw new RuntimeException("模型文件不存在: {$path}");
    }
    $json = file_get_contents($path);
    $model = NaiveBayesModel::fromJson($json);
    return $model->exportModel();
}

function buildTextFromVideo(array $video): string
{
    $parts = [
        $video['title'] ?? '',
        $video['description'] ?? '',
        $video['authorName'] ?? '',
        $video['authorDescription'] ?? '',
    ];
    return trim(implode(' ', array_filter($parts, 'strlen')));
}

function main(): void
{
    $modelPath = __DIR__ . '/model.json';
    $params    = loadModelParams($modelPath);

    $segmenter = getBayesTextSegmenter();

    // 创建输出目录
    $normalDir = __DIR__ . '/predicted_normal';
    $spamDir   = __DIR__ . '/predicted_spam';

    if (!is_dir($normalDir)) {
        mkdir($normalDir, 0766, true);
    } else {
        cleanDir($normalDir);
    }
    if (!is_dir($spamDir)) {
        mkdir($spamDir, 0766, true);
    } else {
        cleanDir($spamDir);
    }

    // 批量加载未标注视频
    $videos = loadUnlabeledVideos(10000);
    if (empty($videos)) {
        echo "没有待预测的未标注视频。\n";
        return;
    }

    $normalCount = 0;
    $spamCount   = 0;

    foreach ($videos as $video) {
        $text = buildTextFromVideo($video);
        if ($text === '') {
            continue;
        }

        $tokens = segmentText($text, $segmenter);
        if (empty($tokens)) {
            continue;
        }

        $result = NaiveBayesPredictor::predictBayesianLabel($params, $tokens);
        $pred   = $result['prediction'];

        // 写入对应目录的 JSON 文件
        $fileName = 'video_' . $video['id'] . '.json';
        if ($pred == 'normal') {
            $outPath = $normalDir . '/' . $fileName;
            $normalCount++;
        } else {
            $outPath = $spamDir . '/' . $fileName;
            $spamCount++;
        }

        $outData = [
            'id'                => $video['id'],
            'title'             => $video['title'] ?? '',
            'description'       => $video['description'] ?? '',
            'authorName'        => $video['authorName'] ?? '',
            'authorDescription' => $video['authorDescription'] ?? '',
            'authorPageUrl'     => $video['authorPageUrl'] ?? '',
            'videoUrl'          => $video['videoUrl'] ?? '',
            'imagePath'         => $video['imagePath'] ?? '',
            'prediction'        => $pred,
            'scores'            => $result['scores'],
        ];

        file_put_contents(
            $outPath,
            json_encode($outData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    echo "预测完成。\n";
    echo "正常视频数: {$normalCount}（写入 predicted_normal/）\n";
    echo "垃圾视频数: {$spamCount}（写入 predicted_spam/）\n";
}

main();
