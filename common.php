<?php
# sqlite3数据库文件路径
define('SQLITE3_FILE', implode(DIRECTORY_SEPARATOR, [__DIR__, 'videos.db'])) or exit('定义 SQLITE3_FILE 失败');
# 图片文件Web访问目录
define('IMAGE_WEB_DIR', '/static/images') or exit('定义 IMAGE_WEB_DIR 失败');
# 图片文件存储路径
define('IMAGE_SAVE_DIR', __DIR__ . '/public/static/images') or exit('定义 IMAGE_SAVE_DIR 失败');
# 日志文件路径
define('LOG_FILE', __DIR__ . '/event.log') or exit('定义 LOG_FILE 失败');
# 特征配置文件
define('FEATURE_CONFIG_FILE', __DIR__ . '/config.json') or exit('定义 FEATURE_CONFIG_FILE 失败');

function saveLog(string $msg): void {
    file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND|LOCK_EX);
}

function initDb(): void {
    $sqlite3dbPath = SQLITE3_FILE;
    if (!file_exists($sqlite3dbPath)) {
        saveLog('数据库文件不存在: ' . $sqlite3dbPath . ' 自动创建');
        $db = new PDO('sqlite:' . $sqlite3dbPath);
        $result = $db->exec('CREATE TABLE videos (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, videoUrl TEXT NOT NULL, imageUrl TEXT NOT NULL, imagePath TEXT NOT NULL, description TEXT NOT NULL, authorName TEXT NOT NULL, authorDescription TEXT NOT NULL, authorPageUrl TEXT NOT NULL, createdAt DATETIME DEFAULT CURRENT_TIMESTAMP)');
        if ($result === false) {
            saveLog('数据库创建失败: ' . $sqlite3dbPath);
            return;
        }
        // 为 videoUrl 添加唯一索引加快存储和查询
        $result = $db->exec('CREATE UNIQUE INDEX videoUrlIndex ON videos (videoUrl)');
        if ($result === false) {
            saveLog('索引videoUrlIndex创建失败: ' . $sqlite3dbPath);
            return;
        }

        // 时间索引
        $result = $db->exec('CREATE INDEX createdAtIndex ON videos (createdAt)');
        if ($result === false) {
            saveLog('索引createdAtIndex创建失败: ' . $sqlite3dbPath);
            return;
        }

        // 创建视频判定结果表。该表每行存储videos视频的AI判定结果，1为判定为正常视频，2为判定为垃圾视频
        $result = $db->exec('CREATE TABLE videoResults (id INTEGER PRIMARY KEY AUTOINCREMENT, videoId INTEGER NOT NULL, result INTEGER NOT NULL, createdAt DATETIME DEFAULT CURRENT_TIMESTAMP)');
        if ($result === false) {
            saveLog('数据库创建失败: ' . SQLITE3_FILE);
            return;
        }
        // 需要对视频ID、记录创建时间建立索引
        $result = $db->exec('CREATE UNIQUE INDEX videoIdIndex ON videoResults (videoId)');
        if ($result === false) {
            saveLog('索引videoIdIndex创建失败: ' . $sqlite3dbPath);
            return;
        }
        $result = $db->exec('CREATE INDEX resultCreatedAtIndex ON videoResults (createdAt)');
        if ($result === false) {
            saveLog('索引resultCreatedAtIndex创建失败: ' . $sqlite3dbPath);
            return;
        }

        $db = null;
    }
}

function getVideosOrderByIdDesc(array $fields = [], int $size = 100, ?int $minId = null): array {
    $fieldsString = implode(',', array_map(function ($field) {
        return '`' . $field . '`';
    }, $fields));
    if (empty($fields)) {
        $fieldsString = '*';
    }
    if ($minId === null) {
        $sqlite3QuerySql = "SELECT $fieldsString FROM videos ORDER BY id DESC LIMIT :size";
        $queryParams = [
            ':size' => $size,
        ];
    } else {
        $sqlite3QuerySql = "SELECT $fieldsString FROM videos WHERE id > :minId ORDER BY id DESC LIMIT :size";
        $queryParams = [
            ':minId' => $minId,
            ':size' => $size,
        ];
    }
    $db = new PDO('sqlite:' . SQLITE3_FILE);
    $stmt = $db->prepare($sqlite3QuerySql);
    if ($stmt === false) {
        saveLog('PDO准备失败: ' . $sqlite3dbPath);
        return [];
    }
    $result = $stmt->execute($queryParams);
    if ($result === false) {
        saveLog('执行失败: ' . $sqlite3dbPath);
        return [];
    }
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($videos === false) {
        saveLog('获取数据失败: ' . $sqlite3dbPath);
        return [];
    }
    return $videos;
}

function showTablesInfo(): void {
    echo "==========\n";
    $db = new PDO('sqlite:' . SQLITE3_FILE);
    $sql = 'SELECT name FROM sqlite_master WHERE type="table"';
    $stmt = $db->prepare($sql);
    $result = $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo '表:' . $table . "\n";
        $sql = 'PRAGMA table_info(' . $table . ')';
        $stmt = $db->prepare($sql);
        $result = $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo $column['name'] . "\n";
        }
        echo "\n";
        // 直接打印表索引：
        echo '索引:' . "\n";
        $sql = 'PRAGMA index_list(' . $table . ')';
        $stmt = $db->prepare($sql);
        $result = $stmt->execute();
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $index) {
            echo $index['name'] . "\n";
        }
    }
    echo "==========\n";
}

function getVideoResult(int $videoId): ?int {
    initDb();
    $db = new PDO('sqlite:' . SQLITE3_FILE);
    if ($db === false) {
        saveLog('数据库打开失败: ' . SQLITE3_FILE);
        return null;
    }

    $sql = 'SELECT videoId,result FROM videoResults WHERE videoId = :videoId ORDER BY id DESC LIMIT 1';
    $params = [
        ':videoId' => $videoId,
    ];
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        saveLog('数据库语句SELECT准备失败: ' . $sql);
        return null;
    }
    $result = $stmt->execute($params);
    if ($result === false) {
        saveLog('数据库语句SELECT执行失败: ' . $sql);
        return null;
    }
    $videoResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($videoResult === false) {
        saveLog('数据库语句SELECT获取数据失败: ' . $sql);
        return null;
    }
    if (count($videoResult) == 0) {
        return 0;
    }
    $first = $videoResult[0];
    return $first['result'];
}

function setVideoResult(int $videoId, int $result): void {
    initDb();
    $db = new PDO('sqlite:' . SQLITE3_FILE);
    if ($db === false) {
        saveLog('数据库打开失败: ' . SQLITE3_FILE);
        return;
    }
    $sql = 'INSERT INTO videoResults (videoId,result) VALUES (:videoId,:result)';
    $params = [
        ':videoId' => $videoId,
        ':result' => $result,
    ];
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        saveLog('数据库语句INSERT准备失败: ' . $sql);
        return;
    }
    $result = $stmt->execute($params);
    if ($result === false) {
        saveLog('数据库语句INSERT执行失败: ' . $sql);
        return;
    }
}

function saveImage(string $imageBlobBase64): ?string {
    // 返回保存路径
    $imagePath = IMAGE_SAVE_DIR . '/' . uniqid() . '.png';

    $imageBlobBase64 = substr($imageBlobBase64, strpos($imageBlobBase64, ',') + 1);
    $imageBlob = base64_decode($imageBlobBase64);
    if (substr($imageBlob, 1, 3) != 'PNG') {
        $hexValue = bin2hex(substr($imageBlob, 1, 3));
        saveLog('图片格式错误:必须是PNG格式,实际：' . $hexValue);
        return null;
    }
    file_put_contents($imagePath, $imageBlob);
    return $imagePath;
}

function saveVideo(array $video): void {
    $imagePath = saveImage($video['imageBase64']);
    if ($imagePath === null) {
        saveLog('图片保存失败: ' . $imagePath);
        return;
    }

    initDb();
    $sqlite3dbPath = SQLITE3_FILE;
    $db = new PDO('sqlite:' . $sqlite3dbPath);
    if ($db === false) {
        saveLog('数据库打开失败: ' . $sqlite3dbPath);
        return;
    }
    $stmt = $db->prepare('INSERT INTO videos (title, videoUrl, imageUrl, imagePath, description, authorName, authorDescription, authorPageUrl) VALUES (:title, :videoUrl, :imageUrl, :imagePath, :description, :authorName, :authorDescription, :authorPageUrl)');
    if ($stmt === false) {
        saveLog('数据库语句INSERT INTO准备失败: ' . $sqlite3dbPath);
        return;
    }
    $result = $stmt->execute([
        ':title' => $video['title'],
        ':videoUrl' => $video['videoUrl'],
        ':imageUrl' => $video['imageUrl'],
        ':imagePath' => $imagePath,
        ':description' => $video['description'],
        ':authorName' => $video['authorName'],
        ':authorDescription' => $video['authorDescription'],
        ':authorPageUrl' => $video['authorPageUrl'],
    ]);
    if ($result === false) {
        saveLog('数据库语句INSERT INTO执行失败: ' . $sqlite3dbPath);
        unlink($imagePath);
        return;
    }
    $stmt = null;
    $db = null;
}

function buildPrompt(array $video, string $attr): string {
    $promptVideoArray = $video;
    unset($promptVideoArray['id']);
    $videoInfoJson = json_encode($promptVideoArray, JSON_UNESCAPED_UNICODE);
    $prompt = <<<VIDEO_PROMPT
视频信息：
```json
${videoInfoJson}
```
判断视频信息中是否具备特征：“${attr}”。如果具备回复“Yes”，如果不具备回复“No”。
VIDEO_PROMPT;
    $prompt = trim($prompt);
    return $prompt;
}

function getCondfig(): array {
    if (!file_exists(FEATURE_CONFIG_FILE)) {
        return [];
    }
    $config = json_decode(file_get_contents(FEATURE_CONFIG_FILE), true);
    if ($config === null) {
        saveLog('配置文件格式错误: ' . FEATURE_CONFIG_FILE . json_last_error_msg());
        return [];
    }
    return $config;
}

function callLLM(string $prompt): ?string {
    sleep(1);
/* 参考文档：https://docs.bigmodel.cn/cn/guide/models/free/glm-4.7-flash
curl -X POST "https://open.bigmodel.cn/api/paas/v4/chat/completions" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer your-api-key" \
-d '{
    "model": "glm-4.7-flash",
    "messages": [
        {
            "role": "user",
            "content": "作为一名营销专家，请为我的产品创作一个吸引人的口号"
        },
        {
            "role": "assistant",
            "content": "当然，要创作一个吸引人的口号，请告诉我一些关于您产品的信息"
        },
        {
            "role": "user",
            "content": "智谱AI 开放平台"
        }
    ],
    "thinking": {
        "type": "enabled"
    },
    "max_tokens": 65536,
    "temperature": 1.0
}'
*/
    $apiKey = getenv('BIGMODEL_API_KEY');
    if ($apiKey === false) {
        saveLog('环境变量BIGMODEL_API_KEY未设置');
        return null;
    }

    $url = 'https://open.bigmodel.cn/api/paas/v4/chat/completions';
    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Bearer ' . $apiKey,
    ];
    $data = [
        'model' => 'glm-4.7-flash',
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'thinking' => [
            'type' => 'disabled', /* 不需要深度思考 */
        ],
        'max_tokens' => 65536,
        'temperature' => 0.6,
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 超时时间60s
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        saveLog('cURL错误: ' . curl_error($ch));
        return null;
    }
    curl_close($ch);
    $result = json_decode($result, true);
    if (empty($result)) {
        saveLog('cURL结果错误: ' . json_last_error_msg() . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        return null;
    }
    if (!isset($result['choices'])) {
        saveLog('cURL结果错误: choices字段不存在' . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        return null;
    }
    if (!isset($result['choices'][0])) {
        saveLog('cURL结果错误: choices[0]字段不存在' . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        return null;
    }
    if (!isset($result['choices'][0]['message'])) {
        saveLog('cURL结果错误: choices[0].message字段不存在' . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        return null;
    }
    if (!isset($result['choices'][0]['message']['content'])) {
        saveLog('cURL结果错误: choices[0].message.content字段不存在' . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        return null;
    }
    return $result['choices'][0]['message']['content'];
}

function buildResult(array $video, array $attrs): ?int {
    echo '判定中，标题：' . $video['title'] . PHP_EOL;
    foreach ($attrs as $attr) {
        echo '判定特征：' . $attr . PHP_EOL;
        $prompt = buildPrompt($video, $attr);
        $return = callLLM($prompt);
        if (is_null($return)) {
            saveLog('LLM调用失败');
            echo 'LLM调用失败' . PHP_EOL;
            return null;
        }
        echo '推理结果：' . $return . PHP_EOL;
        // 转小写
        $result = strtolower($return);
        if (strpos($result, 'yes') !== false) {
            echo '特征已找到' . PHP_EOL;
            return 2;
        }
    }
    return 1;
}

/**
 * 获取未被标注的视频列表
 * @param int $limit 获取数量
 * @return array 视频列表
 */
function getUnlabeledVideos(int $limit = 10): array {
    initDb();
    $db = new PDO('sqlite:' . SQLITE3_FILE);
    if ($db === false) {
        saveLog('数据库打开失败: ' . SQLITE3_FILE);
        return [];
    }

    $sql = "SELECT v.id, v.title, v.videoUrl, v.description, v.authorName, v.authorDescription, v.authorPageUrl
            FROM videos v
            LEFT JOIN videoResults vr ON v.id = vr.videoId
            WHERE vr.id IS NULL
            ORDER BY v.id DESC
            LIMIT :limit";

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        saveLog('数据库语句SELECT准备失败: ' . $sql);
        return [];
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $result = $stmt->execute();

    if ($result === false) {
        saveLog('数据库语句SELECT执行失败: ' . $sql);
        return [];
    }

    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $videos ? $videos : [];
}

function loadUnlabeledVideos(int $limit = 1000): array {
    initDb();
    $db = new PDO('sqlite:' . SQLITE3_FILE);

    $sql = <<<'SQL'
SELECT
    v.id,
    v.title,
    v.description,
    v.authorName,
    v.authorDescription,
    v.videoUrl,
    v.authorPageUrl,
    v.imagePath
FROM videos v
LEFT JOIN videoResults vr ON v.id = vr.videoId
WHERE vr.id IS NULL
ORDER BY v.id ASC
LIMIT :limit
SQL;

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function loadLabeledSamples(): array {
    initDb();
    $db = new PDO('sqlite:' . SQLITE3_FILE);

    $sql = <<<'SQL'
SELECT
    v.id,
    v.title,
    v.description,
    v.authorName,
    v.authorDescription,
    vr.result
FROM videos v
INNER JOIN videoResults vr ON v.id = vr.videoId
ORDER BY v.id ASC
SQL;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cleanDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $filePath = $dir . '/' . $file;
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}
