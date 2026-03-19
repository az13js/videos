<?php
require_once __DIR__ . '/BayesTextSegmenter/vendor/autoload.php';
require_once __DIR__ . '/BayesTextSegmenter/BayesTextSegmenter.php';
require_once __DIR__ . '/NaiveBayesModel/NaiveBayesModel.php';
require_once __DIR__ . '/NaiveBayesModel/NaiveBayesPredictor.php';

/**
 * 获取分词器实例
 * @return BayesTextSegmenter
 */
function getBayesTextSegmenter(): BayesTextSegmenter
{
    return new BayesTextSegmenter(
        //__DIR__ . '/BayesTextSegmenter/dict/spam_terms.txt',
        //__DIR__ . '/BayesTextSegmenter/dict/stop_words.txt'
    );
}

/**
 * 对文本做简单清洗 + 分词
 *
 * 这里简化处理，只用 MODE_DEFAULT
 * 你可以根据需要改成 MODE_SPAM_FOCUS
 * @param string $text
 * @param BayesTextSegmenter $segmenter
 * @param string $mode 分词模式: BayesTextSegmenter::MODE_DEFAULT | BayesTextSegmenter::MODE_SPAM_FOCUS
 * @return array
 */
function segmentText(string $text, BayesTextSegmenter $segmenter, string $mode = BayesTextSegmenter::MODE_DEFAULT): array
{
    $clean = $text;
    // 转小写
    $clean = strtolower($clean);
    // 特殊字符左右加入空格
    $clean = preg_replace('/([^\p{Han}a-zA-Z0-9\s])/u', ' $1 ', $clean);

    // 中文后接英文/数字：在中文与英文/数字间插入空格
    $clean = preg_replace('/([\p{Han}])([a-zA-Z0-9])/u', '$1 $2', $clean);

    // 英文/数字后接中文：在英文/数字与中文间插入空格
    $clean = preg_replace('/([a-zA-Z0-9])([\p{Han}])/u', '$1 $2', $clean);

    // 英文与数字衔接（如"kg123"或"123kg"）：参考数字与单位空格处理逻辑
    $clean = preg_replace('/([a-zA-Z])([0-9])/u', '$1 $2', $clean);
    $clean = preg_replace('/([0-9])([a-zA-Z])/u', '$1 $2', $clean);

    // 将5个及以上连续数字替换为__NUMS__
    $clean = preg_replace('/\d{5,}/', ' __NUMS__ ', $clean);
    $clean = preg_replace('/\s+/', ' ', $clean);
    $clean = trim($clean);

    // BayesTextSegmenter 会返回分词后的数组
    return $segmenter->segmentForBayes($clean, $mode);
}
