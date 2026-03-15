// ==UserScript==
// @name         Bilibili首页视频推荐批量下载助手
// @namespace    http://tampermonkey.net/
// @version      0.5
// @description  在Bilibili网站首页，自动提取当前页面所有推荐视频的信息（标题、UP主、视频链接、封面图），并将封面图片与视频信息打包成一个ZIP压缩文件供下载。支持拖拽悬浮按钮。
// @author       az13js
// @match        *://www.bilibili.com
// @match        *://www.bilibili.com?*
// @match        *://www.bilibili.com/
// @match        *://www.bilibili.com/?*
// @icon         https://www.google.com/s2/favicons?sz=64&domain=bilibili.com
// @grant        none
// @require      http://localhost/static/js/jszip.min.js
// ==/UserScript==

(function() {
    'use strict';

    function addScript(src, callback) {
        const script = document.createElement('script');
        script.src = src;
        document.head.appendChild(script);
        if (callback) {
            script.onload = callback;
        }
    }

    // 标记是否发生了实际的拖拽移动
    let isDragging = false;
    let isDownloading = false;
    const downloadButton = document.createElement('button');

    addScript('http://localhost/static/js/video-info-extractor.js', () => {
        addScript('http://localhost/static/js/neodragvanilla.min.js', () => {
            addScript('http://localhost/static/js/video-info-data-utils.js', () => {
                downloadButton.textContent = '下载视频信息';
                downloadButton.style.position = 'fixed';
                downloadButton.style.top = '10px';
                downloadButton.style.left = '10px';
                downloadButton.style.zIndex = '10000';
                downloadButton.style.padding = '10px';
                downloadButton.style.backgroundColor = '#007bff';
                downloadButton.style.color = 'white';
                downloadButton.style.border = 'none';
                downloadButton.style.borderRadius = '5px';
                document.body.appendChild(downloadButton);

                // 处理点击弹窗逻辑
                downloadButton.addEventListener('click', async (e) => {
                    // 如果刚刚发生了拖拽，则忽略本次点击
                    if (isDragging) {
                        isDragging = false; // 重置状态
                        return;
                    }
                    console.log('执行点击操作');
                    if (isDownloading) {
                        alert('正在下载中，请稍后...');
                        return;
                    }
                    isDownloading = true;
                    await doExtractVideoInfoAndDownload();
                    isDownloading = false;
                });

                new NeoDrag.Draggable(downloadButton, {
                    bounds: 'parent',
                    onDragStart: ({ offsetX, offsetY }) => {
                        isDragging = false;
                    },
                    // 拖拽进行中：只要移动了，就标记为拖拽状态
                    onDrag: ({ offsetX, offsetY }) => {
                        // 只要触发了 onDrag，说明肯定移动了
                        isDragging = true;
                    }
                });
            });
        });
    });

    async function doExtractVideoInfoAndDownload() {
        let allSuccessCount = 0;
        let allFailCount = 0;
        const dataset = await VideoInfoDataUtils.fetchDataBuildDataset((currentIndex, totalCount, successCount, failCount) => {
            downloadButton.textContent = `获取数据(${currentIndex}/${totalCount},Failed:${failCount})`;
            allSuccessCount = successCount;
            allFailCount = failCount;
        });
        const zip = new JSZip();
        let markdownContent = '# Bilibili推荐的视频\n\n';
        let jsonDataset = [];
        for (let data of dataset) {
            const imageFilePathInZip = "images/" + VideoInfoDataUtils.generateRandomFilename('.png');
            zip.file(imageFilePathInZip, data.imageBlob);
            markdownContent += `## [${data.title}](${data.videoUrl})\n\n`;
            markdownContent += `![${data.title}](${imageFilePathInZip})\n\n`;
            if ('' !== data.description) {
                markdownContent += `\`\`\`\n${data.description}\n\`\`\`\n\n`;
            }
            markdownContent += `UP： [${data.authorName}](${data.authorPageUrl})`;
            if ('' !== data.authorDescription) {
                markdownContent += `，简介：\n\n`;
                markdownContent += `\`\`\`\n${data.authorDescription}\n\`\`\`\n\n`;
            } else {
                markdownContent += '\n\n';
            }
            jsonDataset.push({
                "title": data.title,
                "videoUrl": data.videoUrl,
                "imageUrl": data.imageUrl,
                "imageFile": imageFilePathInZip,
                "description": data.description,
                "authorName": data.authorName,
                "authorDescription": data.authorDescription,
                "authorPageUrl": data.authorPageUrl
            });
        }
        const blobMarkdownContent = new Blob([markdownContent], { type: 'text/markdown' });
        zip.file("README.md", blobMarkdownContent);
        const blobJSONContent = new Blob([JSON.stringify(jsonDataset)], { type: 'application/json' });
        zip.file("data.json", blobJSONContent);

        try {
            // 生成压缩包
            const content = await zip.generateAsync({type: 'blob'});

            // 下载压缩包
            const zipFilename = VideoInfoDataUtils.generateRandomFilename('.zip');
            const blobUrl = URL.createObjectURL(content);

            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = zipFilename;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            setTimeout(() => URL.revokeObjectURL(blobUrl), 100);

            alert(`打包完成！成功打包 ${allSuccessCount}，失败 ${allFailCount}`);
        } catch (err) {
            console.error('生成压缩包失败:', err);
            alert('生成压缩包失败！');
        }
    }
})();
