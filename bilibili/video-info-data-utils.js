'use strict';

const VideoInfoDataUtils = {
    /**
     * 获取指定 URL 的 HTML 内容
     * 遵循防御性编程、单一职责与工程化思维
     *
     * @param {string} url - 目标 URL
     * @param {number} [timeout=5000] - 超时时间（毫秒），默认 5 秒
     * @returns {Promise<string>} 返回 HTML 文本内容的 Promise
     */
    async fetchHtmlContent(url, timeout = 5000) {
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
    },

    /**
     * 截取两个界定符之间的文本内容
     * 遵循抽象思维与防御性编程原则
     *
     * @param {string} sourceText - 原始文本
     * @param {string} startMarker - 开始界定符
     * @param {string} endMarker - 结束界定符
     * @returns {string | null} 截取到的内容，如果未找到则返回 null
     */
    extractTextBetween(sourceText, startMarker, endMarker) {
        // 1. 输入校验：确保输入参数合法
        if (typeof sourceText !== 'string' || !sourceText) {
            console.error('错误：原始文本不能为空且必须为字符串');
            return null;
        }
        if (!startMarker || !endMarker) {
            console.error('错误：界定符不能为空');
            return null;
        }

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

        if (endIndex === -1) {
            console.warn(`警告：未找到结束界定符 "${endMarker}"`);
            return null;
        }

        // 5. 截取并返回
        return sourceText.substring(contentStartIndex, endIndex);
    },

    generateRandomFilename(extension) {
        const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < 8; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result + extension;
    },

    // 使用Canvas获取图片Blob - 修复0字节问题
    getImageBlob(url) {
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
    },

    validateVideoInfo(video) {
        return video.imageUrl && video.videoUrl && video.authorName && video.authorPageUrl && video.title;
    },

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
    decodeHtmlEntities(text) {
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
    },

    async fetchAuthorDescription(authorPageUrl) {
        let authorDescription = '';
        try {
            let authorPageHTML = await this.fetchHtmlContent(authorPageUrl, 30000);
            if (authorPageHTML) {
                authorDescription = this.extractTextBetween(authorPageHTML, '，第一时间了解UP主动态。', '"/>');
                if (authorDescription) {
                    return authorDescription;
                }
            }
        } catch (error) {
            console.error('获取作者页面失败:', authorPageUrl, error);
        }
        return null;
    },

    async fetchVideoDescription(videoUrl) {
        let videoDescription = '';
        try {
            let videoPageHTML = await this.fetchHtmlContent(videoUrl, 30000);
            if (videoPageHTML) {
                videoDescription = this.extractTextBetween(videoPageHTML, ',"desc":"', '","desc_v2"');
                if (videoDescription && null !== videoDescription && '-' !== videoDescription && '' !== videoDescription) {
                    // 这个描述里面有\n和HTML转义，这里尝试解除
                    let t = JSON.parse(`"${videoDescription}"`);
                    if (t) {
                        videoDescription = t;
                    }
                    t = this.decodeHtmlEntities(videoDescription);
                    if (t && t !== '') {
                        videoDescription = t;
                    }
                    return videoDescription;
                }
            }
        } catch (error) {
            console.error('获取视频页面失败:', videoUrl, error);
        }
        return null;
    },

    async fetchDataBuildDataset(callback) {
        // extractVideoInfo较快，但是获取数据需要请求服务器比较慢，有必要设计回调函数提示进度
        let dataset = [];
        let videos = [];
        for (let video of extractVideoInfo()) {
            if (this.validateVideoInfo(video)) {
                videos.push(video);
            }
        }
        if (videos.length <= 0) {
            if (callback) {
                callback(0, 0, 0, 0);
            }
            return dataset;
        }
        let successCount = 0;
        let failCount = 0;
        for (let i = 0; i < videos.length; i++) {
            const video = videos[i];
            try {
                // 这三个函数耗时较长，而且有可能获取失败，所以这里要处理异常
                const blob = await this.getImageBlob(video.imageUrl);
                let videoDescription = await this.fetchVideoDescription(video.videoUrl);
                if (!videoDescription) { videoDescription = ''; }

                let authorDescription = await this.fetchAuthorDescription(video.authorPageUrl);
                if (!authorDescription) { authorDescription = ''; }

                dataset.push({
                    "title": video.title,
                    "videoUrl": video.videoUrl,
                    "imageUrl": video.imageUrl,
                    "imageBlob": blob,
                    "description": videoDescription,
                    "authorName": video.authorName,
                    "authorDescription": authorDescription,
                    "authorPageUrl": video.authorPageUrl
                });
                successCount++;
            } catch (err) {
                console.error('获取失败:', video.imageUrl, video.videoUrl, video.authorPageUrl, err);
                failCount++;
            }
            if (callback) {
                callback(i + 1, videos.length, successCount, failCount);
            }
        }
        return dataset;
    }
};
