<?php
declare(strict_types=1);

class NaiveBayesPredictor
{
    /**
     * 独立的贝叶斯预测函数
     *
     * 该函数不依赖任何类实例，仅依赖导出的模型参数结构
     *
     * @param array $modelParams 由 NaiveBayesModel::exportModel() 生成的参数数组
     * @param array $tokens 待预测的文本分词数组（如 ['Java', '教程']）
     * @return array 返回预测结果，包含 'prediction' (预测标签) 和 'scores' (各分类得分详情)
     */
    public static function predictBayesianLabel(array $modelParams, array $tokens): array
    {
        // --- 1. 防御性编程：输入校验 (参考文档 2.1) ---
        // 必须包含 labels, priors, conditionals 三个核心键
        if (!isset($modelParams['labels']) || !isset($modelParams['priors']) || !isset($modelParams['conditionals'])) {
            throw new InvalidArgumentException("无效的模型参数：缺少必要的键。");
        }

        // 如果词表为空或没有训练过，模型无效
        if (empty($modelParams['labels'])) {
            throw new InvalidArgumentException("模型尚未训练，无法进行预测。");
        }

        // --- 2. 核心逻辑：对数概率累加 ---
        $scores = [];

        foreach ($modelParams['labels'] as $label) {
            // 初始化得分为该分类的先验概率
            // 注意：模型参数中存储的是 log 值，所以我们直接使用加法
            $currentScore = $modelParams['priors'][$label];

            // 获取该分类下的条件概率表
            $conditionals = $modelParams['conditionals'][$label];

            foreach ($tokens as $word) {
                // 核心判断：该词是否存在于训练集中？
                if (isset($conditionals[$word])) {
                    // 已知词：直接加上对应的条件概率 log 值
                    $currentScore += $conditionals[$word];
                } else {
                    // 未知词：使用平滑后的默认概率
                    // 兜底逻辑：如果连 __UNKNOWN__ 都没设（理论上不应该），则视为不影响得分(+0)
                    $currentScore += $conditionals['__UNKNOWN__'] ?? 0;
                }
            }

            $scores[$label] = $currentScore;
        }

        // --- 3. 结果判定与返回 ---
        // 找出得分最高的标签
        // 如果得分相同，array_keys 会返回第一个遇到的（即先训练的标签优先）
        $predictedLabel = array_keys($scores, max($scores))[0];

        return [
            'prediction' => $predictedLabel,
            'scores'     => $scores // 返回详细得分供调试或阈值判断
        ];
    }
}
