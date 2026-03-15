/**
 * 默认配置
 */
const EXTRACT_VIDEO_INFO_DEFAULT_CONFIG = {
  // 视频卡容器的类名
  cardClass: 'bili-feed-card',

  // 图片选择器。默认查找卡片内第一个 img 标签。
  imgSelector: 'img',

  // 视频链接选择器，用于缩小查找范围。
  linkSelector: 'a[href]',
  // 视频链接匹配正则。请根据实际情况修改此正则。
  videoLinkPattern: /^https?:\/\/(www\.)?bilibili\.com\/video\/[A-Za-z0-9]+/,

  // 作者名称元素的选择器。
  authorSelector: '.bili-video-card__info--author',

  // 作者主页链接元素的选择器。
  ownerLinkSelector: '.bili-video-card__info--owner',

  // 一个可选的函数，用于对每个卡片进行额外的过滤判断。
  // 如果返回 false，则跳过该卡片。默认不启用。
  cardFilter: null,

  // 是否在控制台打印详细的调试日志。
  debug: false,
};

/**
 * 视频卡提取模块：从页面中提取所有视频卡的信息
 * @param {Object} customConfig - 自定义配置，会覆盖默认配置
 * @param {Element} rootElement - 可选，指定查找的根元素，默认为 document
 * @returns {Array<Object>} 返回一个数组，包含所有有效的视频信息对象
 */
function extractVideoInfo(customConfig = {}, rootElement = document) {
  // 1. 合并配置
  const config = { ...EXTRACT_VIDEO_INFO_DEFAULT_CONFIG, ...customConfig };

  // 2. 查找所有视频卡容器
  const videoCards = Array.from(rootElement.getElementsByClassName(config.cardClass));

  const allResults = []; // 用于存储所有有效的视频信息

  if (videoCards.length === 0) {
    console.warn(`[视频卡提取模块] 未找到任何类名为 "${config.cardClass}" 的视频卡元素。`);
    return allResults;
  }

  if (config.debug) {
    console.info(`[视频卡提取模块] 共找到 ${videoCards.length} 个视频卡，开始处理...`);
  }

  // 3. 遍历每个视频卡
  for (const card of videoCards) {
    // 如果配置了 cardFilter，则先进行判断
    if (typeof config.cardFilter === 'function' && !config.cardFilter(card)) {
      continue;
    }

    const videoInfo = {
      imageUrl: null,
      title: null,
      videoUrl: null,
      authorName: null,
      authorPageUrl: null,
    };

    // --- 提取各项信息 ---

    // 提取视频图片和标题
    const imgElements = card.querySelectorAll(config.imgSelector);
    if (imgElements.length > 0) {
      // 如有多个img，取第一个。
      if (imgElements.length > 1 && config.debug) {
        console.warn(`[视频卡提取模块] 一个视频卡内找到多个图片，默认使用第一个。`, card);
      }
      const img = imgElements[0];

      // 获取图片地址
      const src = img.getAttribute('src');
      if (src && src.trim() !== '') {
        let trimmedSrc = src.trim();
        if (trimmedSrc.startsWith('//')) {
          trimmedSrc = window.location.protocol + trimmedSrc;
        }
        try {
          const url = new URL(trimmedSrc, window.location.href);
          videoInfo.imageUrl = url.href;
        } catch (e) {
          console.warn(`[视频卡提取模块] 图片URL解析失败:`, e);
          videoInfo.imageUrl = trimmedSrc;
        }
      } else {
        console.warn(`[视频卡提取模块] 图片元素的 src 属性为空。`, img);
      }

      // 获取标题
      const alt = img.getAttribute('alt');
      if (alt && alt.trim() !== '') {
        videoInfo.title = alt.trim();
      } else {
        // 有alt但为空，返回null。这里也涵盖了alt属性不存在的情况。
        if (config.debug) console.warn(`[视频卡提取模块] 图片元素的 alt 属性为空或不存在。`, img);
      }
    } else {
      // 如果视频卡内没有img标签，图片和标题相关字段将为null。
      if (config.debug) console.warn(`[视频卡提取模块] 视频卡内未找到图片元素。`, card);
    }

    // 提取视频链接
    const linkElements = card.querySelectorAll(config.linkSelector);
    const candidateLinks = [];

    linkElements.forEach(a => {
      // 避免某些特殊 href 导致正则测试出错。
      try {
        const href = a.href;
        if (href && href.trim() !== '' && !href.startsWith('javascript:') && config.videoLinkPattern.test(href)) {
          candidateLinks.push(href.trim());
        }
      } catch (e) {
        console.error(`[视频卡提取模块] 视频链接匹配时发生错误。`, e, a);
      }
    });

    if (candidateLinks.length > 0) {
      videoInfo.videoUrl = candidateLinks[0]; // 取第一个匹配的链接
      if (candidateLinks.length > 1 && config.debug) {
        console.info(`[视频卡提取模块] 找到多个候选视频链接，默认使用第一个，其余链接如下：`, candidateLinks.slice(1));
      }
    } else {
      console.warn(`[视频卡提取模块] 未找到符合格式的视频链接。`, card);
    }

    // 提取作者名称
    const authorElements = card.querySelectorAll(config.authorSelector);
    if (authorElements.length > 0) {
      if (authorElements.length > 1 && config.debug) {
        console.warn(`[视频卡提取模块] 一个视频卡内找到多个作者名称元素，默认使用第一个。`, card);
      }
      const authorEl = authorElements[0];
      // 只用 title 属性。
      const titleAttr = authorEl.getAttribute('title');
      if (titleAttr && titleAttr.trim() !== '') {
        videoInfo.authorName = titleAttr.trim();
      } else {
        console.warn(`[视频卡提取模块] 作者名称元素的 title 属性为空或不存在。`, authorEl);
      }
    } else {
      console.warn(`[视频卡提取模块] 未找到作者名称元素。`, card);
    }

    // 提取作者个人主页
    const ownerElements = card.querySelectorAll(config.ownerLinkSelector);
    if (ownerElements.length > 0) {
      if (ownerElements.length > 1 && config.debug) {
        console.warn(`[视频卡提取模块] 一个视频卡内找到多个作者主页链接元素，默认使用第一个。`, card);
      }
      const ownerEl = ownerElements[0];
      const href = ownerEl.href;
      if (href && href.trim() !== '' && !href.startsWith('javascript:')) {
        videoInfo.authorPageUrl = href.trim();
      } else {
        console.warn(`[视频卡提取模块] 作者主页链接为空或为javascript伪协议。`, ownerEl);
      }
    } else {
      console.warn(`[视频卡提取模块] 未找到作者主页链接元素。`, card);
    }

    // 4. --- 汇总结果 ---
    // 如果某个视频卡内的视频信息全部都是null，则不返回。
    const hasValidInfo = Object.values(videoInfo).some(value => value !== null);

    if (hasValidInfo) {
      allResults.push(videoInfo);
    } else {
      if (config.debug) console.warn(`[视频卡提取模块] 一个视频卡的所有信息均为null，已跳过。`, card);
    }
  }

  return allResults;
}
