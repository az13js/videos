<?php

use Fukuball\Jieba\Jieba;

/**
 * 贝叶斯垃圾信息过滤专用分词器
 */
class BayesTextSegmenter
{
    // 分词模式常量
    const MODE_DEFAULT = 'default';      // 默认模式（精确模式）
    const MODE_SPAM_FOCUS = 'spam_focus'; // 垃圾信息特征增强模式

    // 默认词典路径
    private $defaultDictPath;
    private $spamDictPath;
    private $stopWordsPath; // 停用词词典路径

    /**
     * 构造函数
     *
     * @param string|null $spamDictPath 垃圾信息专用词典路径
     * @param string|null $stopWordsPath 停用词词典路径
     */
    public function __construct(?string $spamDictPath = null, ?string $stopWordsPath = null)
    {
        // 初始化默认词典
        $this->defaultDictPath = __DIR__ . '/dict/default.txt';

        // 设置垃圾信息词典
        $this->spamDictPath = $spamDictPath ?? __DIR__ . '/dict/spam_terms.txt';

        // 设置停用词词典
        $this->stopWordsPath = $stopWordsPath ?? __DIR__ . '/dict/stop_words.txt';

        $this->initializeSegmenter();
    }

    /**
     * 初始化分词器
     */
    private function initializeSegmenter(): void
    {
        Jieba::init();
        if (file_exists($this->defaultDictPath)) {
            Jieba::loadUserDict($this->defaultDictPath);
        }

        if (file_exists($this->spamDictPath)) {
            Jieba::loadUserDict($this->spamDictPath);
        }

        if (method_exists('Fukuball\Jieba\Finalseg', 'init')) {
            Fukuball\Jieba\Finalseg::init();
        }
    }

    /**
     * 分词主方法
     */
    public function segmentForBayes(string $text, string $mode = self::MODE_DEFAULT): array
    {
        $text = trim($text);
        if (empty($text)) {
            return [];
        }

        if ($mode === self::MODE_SPAM_FOCUS) {
            $words = $this->spamFocusSegment($text);
        } else {
            $words = Jieba::cut($text);
        }

        return $this->postProcessFeatures($words);
    }

    /**
     * 垃圾信息特征增强分词模式
     */
    private function spamFocusSegment(string $text): array
    {
        $words = Jieba::cutForSearch($text);
        $ngrams = $this->extractNgrams($text, 2, 3);
        return array_unique(array_merge($words, $ngrams));
    }

    /**
     * 提取ngram特征
     */
    private function extractNgrams(string $text, int $min = 2, int $max = 3): array
    {
        $ngrams = [];
        $length = mb_strlen($text);

        for ($n = $min; $n <= $max; $n++) {
            for ($i = 0; $i <= $length - $n; $i++) {
                $ngrams[] = mb_substr($text, $i, $n);
            }
        }

        return $ngrams;
    }

    /**
     * 特征后处理
     */
    private function postProcessFeatures(array $words): array
    {
        // 加载停用词与垃圾词列表
        $stopWords = $this->loadStopWords();
        $spamKeywords = $this->loadSpamKeywords();

        return array_filter($words, function ($word) use ($stopWords, $spamKeywords) {
            // 1. 停用词过滤
            if (isset($stopWords[$word])) {
                return false;
            }

            // 2. 保留垃圾信息关键词 (白名单机制)
            if (in_array($word, $spamKeywords)) {
                return true;
            }
            return true;
        });
    }

    /**
     * 加载停用词列表
     */
    private function loadStopWords(): array
    {
        static $stopWordsMap = null;

        if ($stopWordsMap === null) {
            $words = [];
            if ($this->stopWordsPath && file_exists($this->stopWordsPath)) {
                $words = file($this->stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }

            // 转为键值对映射以提升性能
            $stopWordsMap = array_flip($words);
        }

        return $stopWordsMap;
    }

    /**
     * 加载垃圾信息关键词列表
     */
    private function loadSpamKeywords(): array
    {
        static $keywords = null;

        if ($keywords === null) {
            $keywords = [
                //'免费', '优惠', '点击', '链接', '广告',
                //'推广', '赚钱', '兼职', '投资', '回报'
            ];

            if ($this->spamDictPath && file_exists($this->spamDictPath)) {
                $customTerms = file($this->spamDictPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $keywords = array_merge($keywords, $customTerms);
            }
        }

        return $keywords;
    }
}
