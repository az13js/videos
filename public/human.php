<?php
require_once __DIR__ . '/../common.php';

$videos = getUnlabeledVideos(10);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>视频人工标注系统</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; max-width: 900px; margin: 0 auto; padding: 20px; background-color: #f4f4f4; }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
        .stats { text-align: center; margin-bottom: 20px; font-size: 14px; color: #666; }
        .video-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; transition: all 0.3s ease; border-left: 5px solid #3498db; }
        .video-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .video-card.labeled { opacity: 0.5; pointer-events: none; background-color: #e9ecef; border-left-color: #95a5a6; }

        /* 标题与基础信息 */
        .video-title { font-size: 18px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .video-meta { font-size: 13px; color: #7f8c8d; margin-bottom: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
        .video-meta span { margin-right: 10px; }

        /* 内容区域：视频简介与作者简介 */
        .info-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 15px; }
        .info-box { background: #f9f9f9; padding: 12px; border-radius: 4px; border: 1px solid #eee; }
        .info-box h4 { margin: 0 0 8px 0; font-size: 14px; color: #555; }
        .info-box .content { font-size: 14px; color: #333; white-space: pre-wrap; word-wrap: break-word; max-height: 150px; overflow-y: auto; }

        /* 链接样式 */
        .video-link { color: #3498db; text-decoration: none; font-weight: 500; }
        .video-link:hover { text-decoration: underline; }

        /* 操作栏 */
        .action-bar { display: flex; gap: 10px; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background 0.2s; }
        .btn-normal { background-color: #2ecc71; color: white; }
        .btn-normal:hover { background-color: #27ae60; }
        .btn-spam { background-color: #e74c3c; color: white; }
        .btn-spam:hover { background-color: #c0392b; }
        .btn-skip { background-color: #95a5a6; color: white; }
        .btn-skip:hover { background-color: #7f8c8d; }
        .empty-state { text-align: center; padding: 50px; color: #7f8c8d; font-size: 16px; }
    </style>
</head>
<body>
    <h1>视频资源人工标注系统</h1>
    <div class="stats">当前批次加载 <?= count($videos) ?> 条待处理数据</div>

    <?php if (empty($videos)): ?>
        <div class="empty-state">
            暂无待标注视频，任务已完成或数据库为空。
        </div>
    <?php endif; ?>

    <?php foreach ($videos as $video): ?>
        <div class="video-card" id="video-<?= htmlspecialchars($video['id']) ?>">
            <div class="video-title">
                <?= htmlspecialchars($video['title']) ?>
            </div>

            <div class="video-meta">
                <span>
                    <strong>作者：</strong><?= htmlspecialchars($video['authorName']) ?>
                    <?php if (!empty($video['authorPageUrl'])): ?>
                        (<a href="<?= htmlspecialchars($video['authorPageUrl']) ?>" target="_blank" class="video-link">主页</a>)
                    <?php endif; ?>
                </span>
                <span>
                    <strong>原视频：</strong><a href="<?= htmlspecialchars($video['videoUrl']) ?>" target="_blank" class="video-link">点击查看</a>
                </span>
            </div>

            <!-- 详细信息网格 -->
            <div class="info-grid">
                <!-- 视频简介 -->
                <div class="info-box">
                    <h4>📺 视频简介</h4>
                    <div class="content">
                        <?= htmlspecialchars($video['description']) ?>
                    </div>
                </div>

                <!-- 作者简介 -->
                <div class="info-box" style="background-color: #fff8e1; border-color: #ffecb3;">
                    <h4>👤 作者简介</h4>
                    <div class="content">
                        <?= htmlspecialchars($video['authorDescription'] ?? '暂无简介') ?>
                    </div>
                </div>
            </div>

            <div class="action-bar">
                <button class="btn btn-normal" onclick="setLabel(<?= $video['id'] ?>, 1)">正常视频</button>
                <button class="btn btn-spam" onclick="setLabel(<?= $video['id'] ?>, 2)">垃圾视频</button>
                <button class="btn btn-skip" onclick="skipVideo(<?= $video['id'] ?>)">暂时跳过</button>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        function setLabel(videoId, result) {
            const card = document.getElementById('video-' + videoId);
            const formData = new FormData();
            formData.append('videoId', videoId);
            formData.append('result', result);

            fetch('save_label.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 0) {
                    card.classList.add('labeled');
                    card.style.display = 'none';
                } else {
                    alert('操作失败: ' + data.msg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('请求发生错误');
            });
        }

        function skipVideo(videoId) {
            const card = document.getElementById('video-' + videoId);
            card.style.display = 'none';
        }
    </script>
</body>
</html>
