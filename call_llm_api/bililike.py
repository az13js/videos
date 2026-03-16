import argparse
import json
import sys
from typing import Any
import os
import shutil
from local_llm import runModel
from config import get_filter_list

def judge(json_str: str) -> bool:
    for attr in get_filter_list():
        prompt = f'''
视频信息：
```json
{json_str}
```
判断视频信息中是否具备特征：“{attr}”。如果具备回复“Yes”，如果不具备回复“No”。
'''
        print(prompt)
        result = runModel(prompt.strip(), systemPrompt='你是一个视频特征判断专家')
        # 判断是否包含 Yes（忽略大小写）
        result = result.strip().lower()
        if 'yes' in result:
            return True
    return False

def process_video(element: Any, input_file: str, output_file: str) -> str:
    """
    自动化识别我讨厌的视频

    Args:
        element: JSON 列表中的单个元素。

    Returns:
        str: 处理后的字符串。
    """
    # JSON字符串，不需转义UTF8
    if judge(json.dumps(element, ensure_ascii=False)):
        return ''
    else:
        # 如果like文件夹不存在则创建
        if not os.path.exists(output_file):
            os.mkdir(output_file)
        if not os.path.exists(output_file+'/images'):
            os.mkdir(output_file+'/images')
        # 拷贝文件到like/images下
        file_name = os.path.basename(element['imageFile'])
        shutil.copy(input_file + '/' + element['imageFile'], os.path.join(output_file+'/images', file_name))

    markdown_block = f"## [{element['title']}]({element['videoUrl']})\n\n"
    markdown_block += f"![{element['title']}]({element['imageFile']})\n\n"
    if '' != element['description']:
        markdown_block += '```\n'
        markdown_block += f"{element['description']}\n"
        markdown_block += '```\n\n'
    markdown_block += f"UP： [{element['authorName']}]({element['authorPageUrl']})"
    if '' ==  element['authorDescription']:
        markdown_block += '\n\n'
    else:
        markdown_block += '，简介：\n\n'
        markdown_block += '```\n'
        markdown_block += f"{element['authorDescription']}\n"
        markdown_block += '```\n\n'
    return markdown_block

def main():
    # 1. 命令行参数解析
    parser = argparse.ArgumentParser(
        description="JSON 数据处理脚本：读取 JSON 列表，过滤视频后输出为 Markdown 文件。",
        epilog="示例: python bililike.py -f . -o output"
    )

    # -f 和 -o 为必填参数
    parser.add_argument("-f", "--file", required=True, help="输入的 JSON 文件目录")
    parser.add_argument("-o", "--output", required=True, help="输出的 Markdown 文件夹路径")

    args = parser.parse_args()

    input_file = args.file
    output_file = args.output

    # 2. 读取与解析 JSON 文件 (防御性思维：处理文件不存在、格式错误等情况)
    try:
        with open(input_file + '/data.json', 'r', encoding='utf-8') as f:
            data = json.load(f)
    except FileNotFoundError:
        print(f"错误: 找不到文件 '{input_file}/data.json'")
        sys.exit(1)
    except json.JSONDecodeError:
        print(f"错误: 文件 '{input_file}/data.json' 不是合法的 JSON 格式")
        sys.exit(1)
    except Exception as e:
        print(f"错误: 读取文件时发生未知异常 - {e}")
        sys.exit(1)

    # 3. 校验数据结构 (工程思维：确保数据符合预期)
    if not isinstance(data, list):
        print("错误: JSON 解析结果不是一个列表")
        sys.exit(1)

    # 4. 业务处理逻辑
    result_buffer = []

    print(f"开始处理 {len(data)} 个元素...")

    for item in data:
        try:
            # 调用示例函数
            res_str = process_video(item, input_file, output_file)

            # 5. 拼接逻辑：空字符串忽略
            if res_str is not None and res_str != "":
                result_buffer.append(res_str)
        except Exception as e:
            # 防御性编程：单个元素处理失败不应导致程序崩溃
            print(f"警告: 处理元素 '{item}' 时出错: {e}")
            continue

    # 6. 写入 Markdown 文件 (控制 UTF-8 编码)
    try:
        with open(output_file + '/README.md', 'w', encoding='utf-8') as f:
            title = '# Bilibili推荐的视频\n\n'
            f.write(title + ("".join(result_buffer)))
        print(f"成功: 结果已写入 '{output_file}'")
    except IOError as e:
        print(f"错误: 写入 '{output_file}' 失败 - {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
