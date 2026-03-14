# 视频卡提取模块使用指南

> **本文档旨在提供视频卡提取模块（`extractVideoInfo`）的完整使用方法、配置说明与最佳实践，帮助开发者快速、准确地将其集成至自己的项目中。适合需要进行网页数据提取、前端自动化测试或内容聚合开发的工程师参考。**

## 一、 快速开始

**目标**：让模块在你的页面上跑起来，并获取到第一批数据。

**前置条件**：
1. 确保你的项目环境支持现代JavaScript（ES6及以上）。
2. 目标网页已加载完毕，且DOM结构稳定。

**第一步：引入模块**
将提供的代码复制到你的项目文件中（例如 `video-info-extractor.js`），然后在需要使用的地方导入或直接调用。

**第二步：最简调用**
在你的代码中，找到合适的执行时机（如页面加载回调、按钮点击事件中），直接调用函数：

```javascript
// 假设页面已加载完成
const results = extractVideoInfo();
// 查看控制台输出与返回结果
console.log('提取到的视频信息：', results);
```

**第三步：检查结果**

打开浏览器开发者工具的控制台（Console）。
- **成功**：你将看到一个对象数组，每个对象包含 `imageUrl`, `title`, `videoUrl`, `authorName`, `authorPageUrl` 等字段。
- **异常**：若返回空数组或字段为 `null`，请查看控制台中的 `warn` 或 `error` 日志，根据提示检查页面结构或调整配置。

---

## 二、 核心配置详解

模块的强大与灵活性源于其配置项。你可以通过传入一个自定义配置对象来适配不同的网页结构。以下是完整的配置参数说明：

| 配置项 | 类型 | 默认值 | 说明 |
| :--- | :--- | :--- | :--- |
| **`cardClass`** | String | `'bili-feed-card'` | **视频卡容器**的CSS类名。模块会查找所有包含此类的元素作为信息提取的根容器。 |
| **`imgSelector`** | String | `'img'` | 在视频卡内查找**图片元素**的CSS选择器。通常指向封面图。 |
| **`linkSelector`** | String | `'a[href]'` | 在视频卡内查找**链接元素**的CSS选择器，用于进一步筛选视频链接。 |
| **`videoLinkPattern`** | RegExp | `/^https?:\/\/(www\.)?bilibili\.com\/video\/[A-Za-z0-9]+/` | 用于匹配**有效视频链接**的正则表达式。不符合此模式的链接将被忽略。 |
| **`authorSelector`** | String | `'.bili-video-card__info--author'` | 查找**作者名称**元素的CSS选择器。 |
| **`ownerLinkSelector`** | String | `'.bili-video-card__info--owner'` | 查找**作者主页链接**元素的CSS选择器。 |
| **`cardFilter`** | Function | `null` | 一个可选的过滤函数。接收一个DOM元素（视频卡）作为参数，返回 `true` 则处理，返回 `false` 则跳过。适用于高级筛选。 |
| **`debug`** | Boolean | `false` | 开启后，将在控制台输出详细的调试信息，包括多个候选元素的选择过程等，便于开发调试。 |

**配置使用示例**：

```javascript
const myConfig = {
  cardClass: 'my-video-card', // 更新为新类名
  videoLinkPattern: /^https?:\/\/video\.example\.com\/v\/[\w-]+/, // 匹配新的视频URL格式
  debug: true, // 开启调试模式
  // 自定义一个过滤器：只处理数据属性中包含 "type='video'" 的卡片
  cardFilter: (cardElement) => {
    return cardElement.dataset.type === 'video';
  }
};
const videos = extractVideoInfo(myConfig);
```

---

## 三、 使用场景与进阶实践

### 场景一：应对页面结构变化

**问题**：网站升级，视频卡片的类名从 `bili-feed-card` 变更为 `video-card-wrapper`。
**解决方案**：无需修改核心代码，只需在调用时更新配置即可。

```javascript
extractVideoInfo({ cardClass: 'video-card-wrapper' });
```

### 场景二：获取更精准的封面图

**问题**：卡片内存在多个图片（如角标、头像），默认的 `img` 选择器可能定位不准。
**解决方案**：使用更具体的CSS选择器，定位封面图的特定父容器。

```javascript
extractVideoInfo({
  imgSelector: '.video-cover-wrapper img' // 指向封面图容器内的图片
});
```

### 场景三：调试“为何某些字段为null”

**问题**：运行后，部分视频信息的标题或作者为 `null`。
**解决方案**：
1. 开启 `debug: true`，仔细查看控制台的警告信息。
2. 根据日志提示，检查对应元素的DOM结构是否符合预期（如 `alt` 属性是否存在、`title` 属性是否为空）。
3. 根据实际情况调整选择器（`authorSelector`等）或确认页面是否采用懒加载（视野外的卡片可能未渲染完整信息）。

### 场景四：集成到数据采集流水线

**模式**：页面滚动 -> 提取数据 -> 处理数据。

```javascript
async function scrapeAllVideos() {
  // 1. 模拟滚动加载（可选）
  await scrollToBottom(); // 自行实现滚动逻辑
  // 2. 提取数据
  const rawVideos = extractVideoInfo();
  // 3. 数据清洗与标准化（其他模块负责）
  const cleanedVideos = rawVideos.filter(v => v.title && v.videoUrl).map(v => ({
    ...v,
    // 标准化作者名称，去除可能的前缀
    authorName: v.authorName?.replace('UP主：', '').trim()
  }));
  return cleanedVideos;
}
```

---

## 四、 核心原则与设计思想

本模块的设计遵循了“防御性编程”与“配置优于硬编码”的核心原则，这与软件工程中的最佳实践一脉相承：
1.  **默认不可信**：模块默认所有外部信息（DOM结构、属性值）都可能缺失或错误，因此每一步提取都做了 `null` 校验，保证了程序的健壮性。
2.  **隔离变化点**：通过将选择器、正则表达式等易变项提取为配置，将核心提取逻辑与具体页面结构解耦。当页面更新时，只需调整配置，无需重写逻辑，这体现了**抽象思维**中“提取骨架，封装变化”的精髓。
3.  **单一职责**：模块仅负责“从稳定的DOM中提取信息”，而不负责页面加载、滚动触发、数据清洗等其他任务。这种清晰的职责划分，使其易于理解、测试和维护。

---

## 五、 总结

### 核心要点回顾

1.  **即插即用**：直接调用 `extractVideoInfo()` 即可在默认配置下运行。
2.  **配置驱动**：通过传入自定义对象，可灵活适配绝大多数列表式网页的信息提取需求。
3.  **调试友好**：善用 `debug` 选项和返回值中的 `null` 值，结合控制台日志，能快速定位问题。
4.  **健壮优先**：设计上已考虑各种边界情况，确保在一个字段提取失败时，不影响其他字段的提取。
