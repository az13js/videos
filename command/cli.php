<?php

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/../NaiveBayes/helper.php';

function crontabJob(): void {
    initDb();
    return;
    $fields = ['id', 'videoUrl', 'title', 'description', 'authorName', 'authorDescription'];
    $videos = getVideosOrderByIdDesc($fields);
    foreach ($videos as $video) {
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
