<?php

declare(strict_types=1);

/**
 * 朴素贝叶斯分类器模型
 *
 * 专注于概率统计与计算，不处理持久化IO与分词逻辑。
 */
class NaiveBayesModel
{
    /**
     * @var array 各分类下的文档数量 ['S' => 10, 'H' => 20]
     */
    private array $docCount = [];

    /**
     * @var array 各分类下的总词数 ['S' => 100, 'H' => 200]
     */
    private array $wordCount = [];

    /**
     * @var array 词频统计 ['word' => ['S' => 1, 'H' => 5]]
     */
    private array $wordFreq = [];

    /**
     * @var array<string, bool> 词汇表集合（用于动态构建词表）
     */
    private array $vocabulary = [];

    /**
     * @var float 平滑参数
     */
    private float $alpha;

    /**
     * 初始化模型
     * @param float $alpha 拉普拉斯平滑参数，默认为1.0
     */
    public function __construct(float $alpha = 1.0)
    {
        $this->alpha = $alpha;
    }

    /**
     * 训练接口：学习一条样本数据
     *
     * @param string $label 分类标签 (如 'S' 或 'H')
     * @param array $tokens 已经分好词的数组 (由外部处理分词)
     * @return void
     */
    public function train(string $label, array $tokens): void
    {
        if (empty($tokens)) {
            return;
        }

        // 1. 更新文档计数
        if (!isset($this->docCount[$label])) {
            $this->docCount[$label] = 0;
            $this->wordCount[$label] = 0;
        }
        $this->docCount[$label]++;

        // 2. 更新词频统计
        foreach ($tokens as $word) {
            // 动态构建词表
            $this->vocabulary[$word] = true;

            if (!isset($this->wordFreq[$word])) {
                $this->wordFreq[$word] = [];
            }

            if (!isset($this->wordFreq[$word][$label])) {
                $this->wordFreq[$word][$label] = 0;
            }

            $this->wordFreq[$word][$label]++;
            $this->wordCount[$label]++;
        }
    }

    /**
     * 计算并导出模型参数
     *
     * 根据训练数据计算先验概率和条件概率（对数形式）。
     * 这是“训练过程”的最终产出。
     *
     * @return array 包含计算完毕概率的数组结构
     */
    public function exportModel(): array
    {
        $vocabSize = count($this->vocabulary);
        $totalDocs = array_sum($this->docCount);

        $model = [
            'priors' => [],      // 先验概率的对数值： log(P(S)) 和 log(P(H)) ，键是分类标签
            'conditionals' => [], // 条件概率的对数值： log(P(w|S)) 和 log(P(w|H)) ，键是分类标签，值是每个词词频的对数值键值对
            'vocabulary_size' => $vocabSize,
            'labels' => array_keys($this->docCount)
        ];

        if ($totalDocs === 0) {
            return $model;
        }

        foreach ($this->docCount as $label => $count) {
            // 计算先验概率 log(P(S)) 或 log(P(H)) , $label = 'S' 或 'H'
            $model['priors'][$label] = log($count / $totalDocs);

            // 计算条件概率 log(P(w|S)) 或 log(P(w|H))
            // 公式: (N_w,S + alpha) / (N_S + alpha * |V|) 或 (N_w,H + alpha) / (N_H + alpha * |V|)

            $denominator = $this->wordCount[$label] + ($this->alpha * $vocabSize); // 分母： N_S + alpha * |V| 或 N_H + alpha * |V|

            $model['conditionals'][$label] = [];

            // 优化：为了节省内存，这里只计算在训练集中出现过的词的概率
            // 对于未见词，预测时可以使用平滑公式动态计算，或存储一个默认值
            foreach ($this->wordFreq as $word => $counts) {
                $wordCountInLabel = $counts[$label] ?? 0;
                $numerator = $wordCountInLabel + $this->alpha; // 分子： N_w,S + alpha 或 N_w,H + alpha

                // 存储对数概率
                $model['conditionals'][$label][$word] = log($numerator / $denominator); // 计算条件概率 log(P(w|S)) 或 log(P(w|H))
            }

            // 存储该类别下未登录词的默认概率（用于预测时遇到词表外词的情况）
            // 虽然我们在训练时动态构建了词表，但预测数据可能出现训练未见词
            $defaultNumerator = $this->alpha; // 即 0 + alpha
            $model['conditionals'][$label]['__UNKNOWN__'] = log($defaultNumerator / $denominator);
        }

        return $model;
    }

    /**
     * 将当前训练状态序列化为 JSON 字符串
     *
     * 导出的是原始统计数据，便于后续增量训练或传输。
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode([
            'alpha' => $this->alpha,
            'docCount' => $this->docCount,
            'wordCount' => $this->wordCount,
            'wordFreq' => $this->wordFreq,
            'vocabulary' => array_keys($this->vocabulary),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 从 JSON 字符串反序列化恢复模型状态
     *
     * @param string $json
     * @return self
     * @throws \InvalidArgumentException 如果JSON格式错误
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        unset($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON data provided.');
        }

        $model = new self($data['alpha'] ?? 1.0);

        $model->docCount = $data['docCount'] ?? [];
        $model->wordCount = $data['wordCount'] ?? [];
        $model->wordFreq = $data['wordFreq'] ?? [];

        // 恢复词汇表集合
        $vocabArray = $data['vocabulary'] ?? [];
        foreach ($vocabArray as $word) {
            $model->vocabulary[$word] = true;
        }

        return $model;
    }
}
