<?php

require_once __DIR__ . '/common.php';

function crontabJob(): void {
    initDb();
    $attrs = getCondfig();
    $fields = ['id', 'videoUrl', 'title', 'description', 'authorName', 'authorDescription'];
    $videos = getVideosOrderByIdDesc($fields);
    foreach ($videos as $video) {
        $result = getVideoResult($video['id']);
        if (!empty($result)) {
            continue;
        }
        $result = buildResult($video, $attrs);
        if (is_null($result)) {
            continue;
        }
        setVideoResult($video['id'], $result);
    }
}

while (true) {
    try {
        crontabJob();
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
        echo $e->getTraceAsString() . PHP_EOL;
    } catch (Throwable $e) {
        echo $e->getMessage() . PHP_EOL;
        echo $e->getTraceAsString() . PHP_EOL;
    }
    sleep(5);
}
