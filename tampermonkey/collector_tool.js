// ==UserScript==
// @name         B站视频采集器
// @namespace    http://tampermonkey.net/
// @version      0.0.1
// @description  视频数据发送到服务器
// @author       az13js
// @match        *://www.bilibili.com
// @match        *://www.bilibili.com?*
// @match        *://www.bilibili.com/
// @match        *://www.bilibili.com/?*
// @grant        GM_xmlhttpRequest
// ==/UserScript==

(function() {
    'use strict';

    // ==========================================
    // 配置层 (管理配置)
    // ==========================================
    const CONFIG = {
        SERVER_URL: 'http://localhost',
        DEFAULT_TIMEOUT: 5000,           // 默认超时5秒
        MAX_RETRY_COUNT: 3,              // 最大重试次数
        RETRY_DELAY_BASE: 1000,          // 重试基础延迟(ms)
        CONTENT_TYPE_JSON: 'application/json'
    };

    // ==========================================
    // HTTP客户端 (处理网络通信)
    // ==========================================
    const HttpClient = {
        /**
         * 发送POST请求（核心方法，防御性编程）
         * @param {string} endpoint - API端点（如 '/api/videos'）
         * @param {Object} payload - 要发送的数据对象
         * @param {Object} options - 可选配置 {timeout, retryCount, headers}
         * @returns {Promise<Object>} 解析后的响应数据
         */
        async post(endpoint, payload, options = {}) {
            // 防御性校验：参数检查
            if (!endpoint || typeof endpoint !== 'string') {
                throw new Error('HttpClient.post: endpoint must be a non-empty string');
            }
            if (!payload || typeof payload !== 'object') {
                throw new Error('HttpClient.post: payload must be an object');
            }

            const url = this._buildUrl(endpoint);
            const config = {
                timeout: options.timeout || CONFIG.DEFAULT_TIMEOUT,
                retryCount: options.retryCount || 0,
                headers: {
                    'Content-Type': CONFIG.CONTENT_TYPE_JSON,
                    ...options.headers
                }
            };

            return this._sendWithRetry(url, payload, config);
        },

        /**
         * 构建完整URL（封装细节，隔离变化）
         * @private
         */
        _buildUrl(endpoint) {
            const baseUrl = CONFIG.SERVER_URL.replace(/\/$/, ''); // 移除末尾斜杠
            const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
            return `${baseUrl}${cleanEndpoint}`;
        },

        /**
         * 带重试机制的请求发送（防御性：应对临时网络故障）
         * @private
         */
        async _sendWithRetry(url, payload, config, attempt = 1) {
            try {
                return await this._sendRequest(url, payload, config);
            } catch (error) {
                const shouldRetry = attempt < config.retryCount && this._isRetryableError(error);

                if (shouldRetry) {
                    const delay = CONFIG.RETRY_DELAY_BASE * Math.pow(2, attempt - 1); // 指数退避
                    console.warn(`[HttpClient] 请求失败，${delay}ms后第${attempt + 1}次重试...`, error.message);
                    await this._sleep(delay);
                    return this._sendWithRetry(url, payload, config, attempt + 1);
                }

                throw error; // 重试耗尽，向上抛出
            }
        },

        /**
         * 底层请求发送（只负责一次请求）
         * @private
         */
        _sendRequest(url, payload, config) {
            return new Promise((resolve, reject) => {
                const requestData = JSON.stringify(payload);

                GM_xmlhttpRequest({
                    method: 'POST',
                    url: url,
                    headers: config.headers,
                    data: requestData,
                    timeout: config.timeout,

                    onload: (response) => {
                        try {
                            const result = this._handleResponse(response);
                            resolve(result);
                        } catch (error) {
                            reject(error);
                        }
                    },

                    onerror: (error) => {
                        reject(new Error(`Network error: ${error.statusText || 'Unknown'}`));
                    },

                    ontimeout: () => {
                        reject(new Error(`Request timeout after ${config.timeout}ms`));
                    }
                });
            });
        },

        /**
         * 处理响应（防御性：处理各种HTTP状态）
         * @private
         */
        _handleResponse(response) {
            const status = response.status;

            // 成功状态码
            if (status >= 200 && status < 300) {
                try {
                    // 尝试解析JSON，失败则返回原文本
                    const data = response.responseText ? JSON.parse(response.responseText) : null;
                    return {
                        success: true,
                        status: status,
                        data: data,
                        raw: response.responseText
                    };
                } catch (e) {
                    return {
                        success: true,
                        status: status,
                        data: null,
                        raw: response.responseText
                    };
                }
            }

            // 客户端错误 (4xx)
            if (status >= 400 && status < 500) {
                throw new Error(`Client error ${status}: ${response.statusText || 'Bad Request'}`);
            }

            // 服务端错误 (5xx)
            if (status >= 500) {
                throw new Error(`Server error ${status}: ${response.statusText || 'Internal Error'}`);
            }

            throw new Error(`Unexpected status ${status}`);
        },

        /**
         * 判断错误是否可重试（防御性：区分临时故障和逻辑错误）
         * @private
         */
        _isRetryableError(error) {
            const retryablePatterns = [
                /timeout/i,
                /network error/i,
                /5\d\d/,  // 5xx 服务器错误
                /ECONNREFUSED/i
            ];
            return retryablePatterns.some(pattern => pattern.test(error.message));
        },

        /**
         * 延迟工具（辅助方法）
         * @private
         */
        _sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    };

    // ==========================================
    // 视频采集器 (处理业务逻辑)
    // ==========================================
    const VideoCollector = {
        /**
         * 批量发送视频（优化网络开销）
         * @param {Array} videoList - 视频信息数组
         */
        async sendBatch(videoList) {
            if (!Array.isArray(videoList) || videoList.length === 0) {
                return { success: false, error: 'Empty batch' };
            }

            const validVideos = videoList.filter(v => this._isValidVideoInfo(v));

            const payload = {
                videos: validVideos,
                batchSize: validVideos.length,
                collectedAt: new Date().toISOString()
            };

            try {
                const result = await HttpClient.post('/api_videos_batch.php', payload, {
                    retryCount: CONFIG.MAX_RETRY_COUNT
                });
                console.log(`[VideoCollector] 批量发送成功: ${validVideos.length} 个视频`);
                return { success: true, data: result };
            } catch (error) {
                console.error('[VideoCollector] 批量发送失败:', error.message);
                return { success: false, error: error.message };
            }
        },

        /**
         * 校验视频信息格式（确保数据质量）
         * @private
         */
        _isValidVideoInfo(info) {
            return info &&
                typeof info === 'object' &&
                typeof info.title === 'string' &&
                typeof info.videoUrl === 'string' &&
                typeof info.imageUrl === 'string' &&
                typeof info.imageBase64 === 'string' &&
                typeof info.description === 'string' &&
                typeof info.authorName === 'string' &&
                typeof info.authorDescription === 'string' &&
                typeof info.authorPageUrl === 'string';
        }
    };

    function addScript(src, callback) {
        const script = document.createElement('script');
        script.src = src;
        document.head.appendChild(script);
        if (callback) {
            script.onload = callback;
        }
    }

    addScript('http://localhost/static/js/video-info-extractor.js', () => {
        addScript('http://localhost/static/js/video-info-data-utils.js', () => {
            console.log('[VideoCollector] 脚本已加载完成。');
            collectorMain();
        });
    });

    const Worker = {
        _cachePushedVideoUrls: new Set(),
        async run() {
            let dataset = await VideoInfoDataUtils.fetchDataBuildDataset((currentIndex, totalCount, successCount, failCount) => {
                console.log(`[Worker] 已获取第 ${currentIndex} 个视频，总共 ${totalCount} 个视频，成功 ${successCount} 个，失败 ${failCount} 个`);
            });
            let theVideosNeedToPush = [];
            for (let data of dataset) {
                if (this._cachePushedVideoUrls.has(data.videoUrl)) {
                    console.log(`[Worker] 视频 ${data.title} 已推送，跳过`);
                    continue;
                }
                let pushItem = {
                    title: data.title,
                    videoUrl: data.videoUrl,
                    imageUrl: data.imageUrl,
                    imageBase64: await VideoInfoDataUtils.blobToBase64(data.imageBlob),
                    description: data.description,
                    authorName: data.authorName,
                    authorDescription: data.authorDescription,
                    authorPageUrl: data.authorPageUrl
                };
                if (!pushItem.description) {
                    pushItem.description = '';
                }
                if (!pushItem.authorDescription) {
                    pushItem.authorDescription = '';
                }
                theVideosNeedToPush.push(pushItem);
            }
            if (theVideosNeedToPush.length === 0) {
                console.log('[Worker] 没有新的视频需要推送');
                return dataset;
            }
            // 把 theVideosNeedToPush 拆分为每次5个视频进行推送，防止一批推送失败
            for (let i = 0; i < theVideosNeedToPush.length; i += 5) {
                let batch = theVideosNeedToPush.slice(i, i + 5);
                const result = await VideoCollector.sendBatch(batch);
                if (result.success) {
                    console.log('[Worker] 批量推送成功:', result.data);
                    for (let item of batch) {
                        this._cachePushedVideoUrls.add(item.videoUrl);
                    }
                } else {
                    console.error('[Worker] 批量推送失败:', result.error);
                }
            }
            return dataset;
        }
    }

    async function collectorMain() {
        console.log('[VideoCollector] 开始执行任务...')
        await Worker.run();
        console.log('[VideoCollector] 任务完成，准备下一轮')
        setTimeout(collectorMain, 5000);
    }

})();
