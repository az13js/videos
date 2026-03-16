from __future__ import annotations

import json
import logging
import time
from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

# -----------------------------
# 1. 日志配置（可按实际项目调整）
# -----------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
)
logger = logging.getLogger("local_model_client")

# -----------------------------
# 2. 配置与异常定义
# -----------------------------
@dataclass
class ModelConfig:
    """
    本地大模型服务的配置（抽象：隔离变化，便于扩展）
    """

    # 服务地址，例如 http://127.0.0.1:8080/v1/chat/completions
    endpoint: str = "http://127.0.0.1:8080/v1/chat/completions"

    # 模型名称，某些后端需要；可忽略
    model: str = "gpt-3.5-turbo-0613"

    # 默认采样参数
    temperature: float = 0.6

    # 连接超时 / 读取超时（秒）
    connect_timeout: float = 5.0
    read_timeout: float = 60.0

    # 重试策略
    max_retries: int = 3
    backoff_factor: float = 1.0
    retry_status_codes: List[int] = field(
        default_factory=lambda: [502, 503, 504]
    )


class LocalModelClientError(Exception):
    """本地模型客户端统一异常基类"""

    pass


class ModelRequestError(LocalModelClientError):
    """请求模型服务失败（网络 / 超时 / 非 200 响应等）"""

    pass


class ModelResponseParseError(LocalModelClientError):
    """响应体解析失败或缺少必要字段"""

    pass


# -----------------------------
# 3. 核心逻辑：拆分成函数
# -----------------------------
def _build_messages(user_prompt: str, system_prompt: str) -> List[Dict[str, str]]:
    """
    构建 messages 数组。
    文档要求：把系统提示词合并到第一条用户消息里，兼容不支持 system 的模型。
    """
    if not isinstance(user_prompt, str):
        raise ModelRequestError("user_prompt 必须是字符串")

    messages: List[Dict[str, str]] = []

    # 如果有系统提示词，将其放在第一条用户消息开头
    if system_prompt:
        if not isinstance(system_prompt, str):
            raise ModelRequestError("system_prompt 必须是字符串")
        # 简单合并，格式与文档示例一致
        combined = f"{system_prompt}\n\n{user_prompt}"
        messages.append({"role": "user", "content": combined})
    else:
        messages.append({"role": "user", "content": user_prompt})

    return messages


def _build_request_body(
    messages: List[Dict[str, str]],
    config: ModelConfig,
    **extra_params: Any,
) -> Dict[str, Any]:
    """
    构建请求体，允许通过 extra_params 覆盖参数（例如 temperature、max_tokens 等）。
    """
    body: Dict[str, Any] = {
        "model": config.model,
        "messages": messages,
        "temperature": extra_params.get("temperature", config.temperature),
    }

    # 其他参数透传（如 top_p、max_tokens 等）
    for k, v in extra_params.items():
        if k not in body and v is not None:
            body[k] = v

    return body


def _create_session_with_retry(config: ModelConfig) -> requests.Session:
    """
    创建带重试策略的 Session，符合 requests 最佳实践<span data-allow-html class='source-item source-aggregated' data-group-key='source-group-3' data-url='https://python-requests.org/python-requests-timeout/' data-id='turn2fetch0'><span data-allow-html class='source-item-num' data-group-key='source-group-3' data-id='turn2fetch0' data-url='https://python-requests.org/python-requests-timeout/'><span class='source-item-num-name' data-allow-html>python-requests.org</span><span data-allow-html class='source-item-num-count'></span></span></span>。
    """
    retry_strategy = Retry(
        total=config.max_retries,
        backoff_factor=config.backoff_factor,
        status_forcelist=config.retry_status_codes,
        allowed_methods=["POST"],  # 只对 POST 方法重试
    )
    adapter = HTTPAdapter(max_retries=retry_strategy)
    session = requests.Session()
    session.mount("http://", adapter)
    session.mount("https://", adapter)
    return session


def _post_json(
    session: requests.Session,
    url: str,
    body: Dict[str, Any],
    config: ModelConfig,
) -> Dict[str, Any]:
    """
    发送 POST 请求，返回 JSON 响应体。
    包含防御性编程：超时、状态码校验、JSON 格式校验。
    """
    try:
        timeout = (config.connect_timeout, config.read_timeout)  # (connect, read)

        logger.debug(
            "Sending request to %s: %s",
            url,
            json.dumps(body, ensure_ascii=False),
        )

        start_time = time.time()
        response = session.post(
            url,
            json=body,
            headers={"Content-Type": "application/json"},
            timeout=timeout,
        )
        elapsed = time.time() - start_time

        logger.info(
            "Request to %s completed in %.3fs, status=%s",
            url,
            elapsed,
            response.status_code,
        )

        # 防御性：非 200 状态码视为失败
        if not response.ok:
            raise ModelRequestError(
                f"Model API returned {response.status_code}: {response.text}"
            )

        # 尝试解析 JSON
        try:
            resp_json = response.json()
        except json.JSONDecodeError as exc:
            raise ModelResponseParseError(
                f"Invalid JSON response from model: {response.text}"
            ) from exc

        return resp_json

    except requests.Timeout as exc:
        logger.warning(
            "Timeout while calling model endpoint %s (timeout=%s)",
            url,
            (config.connect_timeout, config.read_timeout),
        )
        raise ModelRequestError("Timeout while calling model endpoint") from exc

    except requests.RequestException as exc:
        logger.exception("Network error while calling model endpoint %s", url)
        raise ModelRequestError("Network error while calling model endpoint") from exc


def _extract_content(resp_json: Dict[str, Any]) -> str:
    """
    从 OpenAI 风格的响应中提取 assistant 回复内容。
    响应结构示例：
    {
        "choices": [
            {
                "message": {
                    "role": "assistant",
                    "content": "..."
                }
            }
        ]
    }
    """
    try:
        choices = resp_json.get("choices")
        if not choices or not isinstance(choices, list):
            raise ModelResponseParseError(
                "Response missing or invalid 'choices' array"
            )

        message = choices[0].get("message")
        if not message or not isinstance(message, dict):
            raise ModelResponseParseError(
                "First choice missing or invalid 'message' object"
            )

        content = message.get("content")
        if not isinstance(content, str):
            raise ModelResponseParseError(
                "message.content is not a string"
            )

        return content

    except (AttributeError, KeyError, IndexError) as exc:
        raise ModelResponseParseError(
            f"Failed to parse content from response: {resp_json}"
        ) from exc


# -----------------------------
# 4. 对外暴露的主函数：runModel
# -----------------------------
def runModel(
    userPrompt: str,
    systemPrompt: str = "",
    *,
    config: Optional[ModelConfig] = None,
    **extra_params: Any,
) -> str:
    """
    调用本地兼容 OpenAI 接口的大模型服务，返回模型生成的文本内容。

    Args:
        userPrompt: 用户输入。
        systemPrompt: 系统提示词，会被合并到第一条用户消息中。
        config: 模型配置，如 endpoint、model、temperature、超时、重试策略等。
        extra_params: 其他透传给模型的参数（如 top_p、max_tokens 等）。

    Returns:
        模型生成的文本内容。

    Raises:
        ModelRequestError: 请求失败（网络 / 超时 / 非 200 响应）。
        ModelResponseParseError: 响应解析失败或缺少必要字段。
    """
    # 1. 配置初始化（防御性：默认值）
    if config is None:
        config = ModelConfig()

    # 2. 构造 messages（文档要求：系统提示词合并到用户输入）
    messages = _build_messages(userPrompt, systemPrompt)

    # 3. 构造请求体
    body = _build_request_body(messages, config, **extra_params)

    # 4. 创建带重试的 Session
    session = _create_session_with_retry(config)

    try:
        # 5. 发送请求
        resp_json = _post_json(session, config.endpoint, body, config)

        # 6. 提取内容
        content = _extract_content(resp_json)

        # 7. 日志：记录 token 使用情况（如有）
        usage = resp_json.get("usage")
        if usage and isinstance(usage, dict):
            logger.info(
                "Token usage: prompt_tokens=%s, completion_tokens=%s, total_tokens=%s",
                usage.get("prompt_tokens"),
                usage.get("completion_tokens"),
                usage.get("total_tokens"),
            )

        return content

    finally:
        # 关闭 Session，避免资源泄漏
        session.close()


# -----------------------------
# 5. 使用示例（本地测试）
# -----------------------------
if __name__ == "__main__":
    cfg = ModelConfig(
        endpoint="http://127.0.0.1:8080/v1/chat/completions",
        model="gpt-3.5-turbo-0613",
        temperature=0.6,
        connect_timeout=5.0,
        read_timeout=60.0,
        max_retries=3,
    )

    try:
        result = runModel(
            userPrompt="Hello",
            systemPrompt="You are a helpful assistant.",
            config=cfg,
        )
        print("Model response:", result)
    except LocalModelClientError as e:
        logger.error("Failed to call local model: %s", e)
