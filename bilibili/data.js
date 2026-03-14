// ==UserScript==
// @name         Bilibili首页视频推荐批量下载助手
// @namespace    http://tampermonkey.net/
// @version      0.4
// @description  在Bilibili网站首页，自动提取当前页面所有推荐视频的信息（标题、UP主、视频链接、封面图），并将封面图片与视频信息打包成一个ZIP压缩文件供下载。支持拖拽悬浮按钮。
// @author       az13js
// @match        *://www.bilibili.com
// @match        *://www.bilibili.com?*
// @match        *://www.bilibili.com/
// @match        *://www.bilibili.com/?*
// @icon         https://www.google.com/s2/favicons?sz=64&domain=bilibili.com
// @grant        none
// @require      https://example.cn/dict/jszip.min.js
// ==/UserScript==

(function() {
    'use strict';

    /**
     * 获取指定 URL 的 HTML 内容
     * 遵循防御性编程、单一职责与工程化思维
     *
     * @param {string} url - 目标 URL
     * @param {number} [timeout=5000] - 超时时间（毫秒），默认 5 秒
     * @returns {Promise<string>} 返回 HTML 文本内容的 Promise
     */
    async function fetchHtmlContent(url, timeout = 5000) {
        // 1. 输入校验：防御的第一道防线
        if (!url || typeof url !== 'string') {
            throw new Error('Invalid Input: URL must be a non-empty string.');
        }

        // 业务逻辑合法性检查：确保协议合规
        if (!url.startsWith('http://') && !url.startsWith('https://')) {
            throw new Error('Invalid Protocol: URL must start with http:// or https://');
        }

        // 2. 资源管理：防止请求无限期挂起（设置超时机制）
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                headers: {
                    // 明确告知服务器我们需要什么类型的数据
                    'Accept': 'text/html,application/xhtml+xml,application/xml'
                }
            });

            // 3. 清理资源：无论成功失败，都要清除定时器
            clearTimeout(timeoutId);

            // 4. 状态检查：HTTP 状态码异常处理（优雅降级的基础）
            if (!response.ok) {
                throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
            }

            // 5. 内容类型校验：防止非 HTML 内容的误处理
            const contentType = response.headers.get('content-type');
            if (contentType && !contentType.includes('text/html')) {
                throw new Error(`Invalid Content-Type: Expected HTML but received ${contentType}`);
            }

            // 返回处理后的文本数据
            return await response.text();

        } catch (error) {
            // 确保超时后清理定时器
            clearTimeout(timeoutId);

            // 6. 异常处理与观测性：区分错误类型，抛出清晰的错误信息
            if (error.name === 'AbortError') {
                throw new Error(`Network Timeout: Request exceeded ${timeout}ms`);
            }

            // 重新抛出错误，保留调用栈信息
            throw error;
        }
    }

    /**
     * 截取两个界定符之间的文本内容
     * 遵循抽象思维与防御性编程原则
     *
     * @param {string} sourceText - 原始文本
     * @param {string} startMarker - 开始界定符
     * @param {string} endMarker - 结束界定符
     * @returns {string | null} 截取到的内容，如果未找到则返回 null
     */
    function extractTextBetween(sourceText, startMarker, endMarker) {
        // ==========================================
        // 第一部分：防御性思维——“永远不要相信外部世界”
        // ==========================================

        // 1. 输入校验：确保输入参数合法
        if (typeof sourceText !== 'string' || !sourceText) {
            console.error('错误：原始文本不能为空且必须为字符串');
            return null;
        }
        if (!startMarker || !endMarker) {
            console.error('错误：界定符不能为空');
            return null;
        }

        // ==========================================
        // 第二部分：抽象思维——“从细节中提取骨架”
        // ==========================================

        // 2. 查找开始位置
        const startIndex = sourceText.indexOf(startMarker);

        // 防御性判断：如果找不到开始标记，直接返回
        if (startIndex === -1) {
            console.warn(`警告：未找到开始界定符 "${startMarker}"`);
            return null;
        }

        // 3. 计算内容实际起始位置（开始标记之后）
        // 这是一个“变化”的逻辑点：不同的界定符长度不同，所以要用计算而非写死数字
        const contentStartIndex = startIndex + startMarker.length;

        // 4. 查找结束位置（从开始位置之后找，提高效率并避免逻辑错误）
        const endIndex = sourceText.indexOf(endMarker, contentStartIndex);

        // 防御性判断：如果找不到结束标记
        if (endIndex === -1) {
            console.warn(`警告：未找到结束界定符 "${endMarker}"`);
            return null;
        }

        // ==========================================
        // 第三部分：沟通思维——“命名即文档”
        // ==========================================

        // 5. 截取并返回
        return sourceText.substring(contentStartIndex, endIndex);
    }

    function addScript(src, callback) {
        const script = document.createElement('script');
        script.src = src;
        document.head.appendChild(script);
        if (callback) {
            script.onload = callback;
        }
    }

    function generateRandomFilename(extension) {
        const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < 8; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result + extension;
    }

    // 使用Canvas获取图片Blob - 修复0字节问题
    function getImageBlob(url) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';

            img.onload = function() {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);

                    canvas.toBlob((blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Canvas toBlob failed'));
                        }
                    }, 'image/png');
                } catch (err) {
                    reject(err);
                }
            };

            img.onerror = function(err) {
              console.error('图片加载失败:', url, err);
              reject(new Error(`Image load failed: ${url}`));
            };

            img.src = url;
        });
    }

    function validateVideoInfo(video) {
        return video.imageUrl && video.videoUrl && video.authorName && video.authorPageUrl && video.title;
    }

    /**
     * 将 HTML 实体字符转换为原始文本
     * 例如："&lt;div&gt;" -> "<div>", "&nbsp;" -> " "
     *
     * 符合原则：
     * 1. KISS原则：利用浏览器原生 innerHTML 机制自动解码，代码极简。
     * 2. 性能优先：避免创建完整的 DOMParser 文档对象。
     * 3. 防御性编程：处理空值与边界情况。
     *
     * @param {string} text 含有 HTML 实体的字符串
     * @returns {string} 解码后的原始字符串
     */
    function decodeHtmlEntities(text) {
        // 1. 防御性思维：输入校验
        if (typeof text !== 'string') {
            console.warn('decodeHtmlEntities: 输入参数必须为字符串');
            return '';
        }

        // 2. 快速路径：如果没有包含实体的特征，直接返回，节省性能开销
        if (!text.includes('&')) {
            return text;
        }

        // 3. 核心逻辑：利用 DOM 元素的 innerHTML 自动解码特性
        // 创建一个不在 DOM 树中的临时元素，避免重绘页面
        const tempElement = document.createElement('textarea');

        // 赋值 innerHTML，浏览器会自动解析实体
        tempElement.innerHTML = text;

        // 读取 value 或 textContent 获取解码后的文本
        return tempElement.value;
    }

    async function fetchAuthorDescription(authorPageUrl) {
        let authorDescription = '';
        try {
            let authorPageHTML = await fetchHtmlContent(authorPageUrl, 30000);
            if (authorPageHTML) {
                authorDescription = extractTextBetween(authorPageHTML, '，第一时间了解UP主动态。', '"/>');
                if (authorDescription) {
                    return authorDescription;
                }
            }
        } catch (error) {
            console.error('获取作者页面失败:', authorPageUrl, error);
        }
        return null;
    }

    async function fetchVideoDescription(videoUrl) {
        let videoDescription = '';
        try {
            let videoPageHTML = await fetchHtmlContent(videoUrl, 30000);
            if (videoPageHTML) {
                videoDescription = extractTextBetween(videoPageHTML, ',"desc":"', '","desc_v2"');
                if (videoDescription && null !== videoDescription && '-' !== videoDescription && '' !== videoDescription) {
                    return videoDescription;
                }
            }
        } catch (error) {
            console.error('获取视频页面失败:', videoUrl, error);
        }
        return null;
    }

    async function doExtractVideoInfoAndDownload() {
        let videos = [];
        let jsonDataset = [];
        for (let v of extractVideoInfo()) {
            if (validateVideoInfo(v)) {
                videos.push(v);
            }
        }
        downloadButton.textContent = `正在打包中(0/${videos.length},Failed:0)`;
        const zip = new JSZip();
        let successCount = 0;
        let failCount = 0;
        let markdownContent = '# Bilibili推荐的视频\n\n';
        for (let i = 0; i < videos.length; i++) {
            downloadButton.textContent = `正在打包中(${i+1}/${videos.length},Failed:${failCount})`;
            const imageFilePathInZip = "images/" + generateRandomFilename('.png');
            try {
                // 使用Canvas获取图片Blob
                const blob = await getImageBlob(videos[i].imageUrl);
                zip.file(imageFilePathInZip, blob);
                successCount++;

                let authorDescriptionOrigin = await fetchAuthorDescription(videos[i].authorPageUrl);
                let authorDescription = authorDescriptionOrigin;
                if (authorDescription) {
                    authorDescription = `\`\`\`\n${authorDescription}\n\`\`\`\n\n`;
                } else {
                    authorDescription = '';
                    authorDescriptionOrigin = '';
                }
                let videoDescriptionOrigin = await fetchVideoDescription(videos[i].videoUrl);
                let videoDescription = videoDescriptionOrigin;
                if (videoDescription) {
                    let t = JSON.parse(`"${videoDescription}"`);
                    if (t) {
                        videoDescription = t;
                    }
                    t = decodeHtmlEntities(videoDescription);
                    if (t && t !== '') {
                        videoDescription = t;
                        videoDescriptionOrigin = t;
                    }
                    videoDescription = `\`\`\`\n${videoDescription}\n\`\`\`\n\n`;
                } else {
                    videoDescription = '';
                    videoDescriptionOrigin = '';
                }

                markdownContent += `## [${videos[i].title}](${videos[i].videoUrl})\n\n`;
                markdownContent += `![${videos[i].title}](${imageFilePathInZip})\n\n`;
                markdownContent += `${videoDescription}`;
                markdownContent += `UP： [${videos[i].authorName}](${videos[i].authorPageUrl})，简介：\n\n`;
                markdownContent += `${authorDescription}`;
                jsonDataset.push({
                    "title": videos[i].title,
                    "videoUrl": videos[i].videoUrl,
                    "imageUrl": videos[i].imageUrl,
                    "imageFile": imageFilePathInZip,
                    "description": videoDescriptionOrigin,
                    "authorName": videos[i].authorName,
                    "authorDescription": authorDescriptionOrigin,
                    "authorPageUrl": videos[i].authorPageUrl
                });
            } catch (err) {
                console.error('获取失败:', videos[i].imageUrl, err);
                failCount++;
                downloadButton.textContent = `正在打包中(${i+1}/${videos.length},Failed:${failCount})`;
            }
        }
        const blobMarkdownContent = new Blob([markdownContent], { type: 'text/markdown' });
        zip.file("README.md", blobMarkdownContent);
        const blobJSONContent = new Blob([JSON.stringify(jsonDataset)], { type: 'application/json' });
        zip.file("data.json", blobJSONContent);

        try {
            // 生成压缩包
            const content = await zip.generateAsync({type: 'blob'});

            // 下载压缩包
            const zipFilename = generateRandomFilename('.zip');
            const blobUrl = URL.createObjectURL(content);

            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = zipFilename;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            setTimeout(() => URL.revokeObjectURL(blobUrl), 100);

            alert(`打包完成！成功打包 ${successCount}，失败 ${failCount}`);
        } catch (err) {
            console.error('生成压缩包失败:', err);
            alert('生成压缩包失败！');
        }
    }

    // 标记是否发生了实际的拖拽移动
    let isDragging = false;
    let isDownloading = false;
    const downloadButton = document.createElement('button');

    addScript('https://example.cn/dict/video-info-extractor.js', () => {
        addScript('https://example.cn/dict/neodragvanilla.min.js', () => {
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
})();
