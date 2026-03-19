<?php
/**
 * 训练脚本：利用已标注视频训练朴素贝叶斯文本分类模型
 */

declare(strict_types=1);

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/helper.php';

/**
 * 将一条视频记录拼接成一段文本
 */
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
    $samples = loadLabeledSamples();
    if (empty($samples)) {
        echo "没有已标注样本，请先用 human.php 进行人工标注。\n";
        return;
    }

    // 初始化分词器
    $segmenter = getBayesTextSegmenter();

    // 初始化模型（拉普拉斯平滑系数 1.0）
    $model = new NaiveBayesModel(1.0);

    $normalCount = 0;
    $spamCount   = 0;

    foreach ($samples as $row) {
        $text = buildTextFromVideo($row);
        if ($text === '') {
            continue;
        }

        $tokens = segmentText($text, $segmenter);
        if (empty($tokens)) {
            continue;
        }

        // result: 1 = 正常, 2 = 垃圾
        $label = $row['result'] == 2 ? 'spam' : 'normal';
        $model->train($label, $tokens);

        if ($label === 'normal') {
            $normalCount++;
        } else {
            $spamCount++;
        }
    }

    echo "训练样本数: " . count($samples) . "\n";
    echo "正常样本: {$normalCount}, 垃圾样本: {$spamCount}\n";

    // 导出模型参数
    $params = $model->exportModel();

    // 保存为 JSON（也可以用 toJson，这里为了兼容预测脚本，用 exportModel 即可）
    $json = $model->toJson();
    $modelPath = __DIR__ . '/model.json';

    file_put_contents($modelPath, $json);
    echo "模型已保存到: {$modelPath}\n";

    // 简单验证：对训练集自身做一次预测（可选）
    $correct = 0;
    foreach ($samples as $row) {
        $text = buildTextFromVideo($row);
        $tokens = segmentText($text, $segmenter);
        if (empty($tokens)) {
            continue;
        }
        $result = NaiveBayesPredictor::predictBayesianLabel($params, $tokens);
        $pred = $result['prediction'];
        $trueLabel = $row['result'] == 2 ? 'spam' : 'normal';
        if ($pred === $trueLabel) {
            $correct++;
        }
    }
    $total = count($samples);
    $acc = $total > 0 ? $correct / $total : 0.0;
    printf("训练集自测准确率: %.2f%% (%d / %d)\n", $acc * 100, $correct, $total);
}

main();
