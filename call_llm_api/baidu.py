'''
文心一言
使用需要安装
pip install qianfan
需要设置环境变量
QIANFAN_ACCESS_KEY=<参考百度相关文档获取的access key>
QIANFAN_SECRET_KEY=<参考百度相关文档获取的secret key>
BAIDU_MODEL=ERNIE-Lite-8K
最后一个是可选的，qianfan模块不会读取这个环境变量但是脚本用它选择模型。模型需要提前申请开通。
'''

import os

os.environ['QIANFAN_ACCESS_KEY']='<参考百度相关文档获取的access key>'
os.environ['QIANFAN_SECRET_KEY']='<参考百度相关文档获取的secret key>'
os.environ['BAIDU_MODEL']='ERNIE-Speed-128K' if os.environ.get('BAIDU_MODEL') is None else os.environ.get('BAIDU_MODEL')
#os.environ['BAIDU_MODEL']='ERNIE-Lite-8K'

import qianfan
import argparse

if 'BAIDU_MODEL' not in os.environ:
    os.environ['BAIDU_MODEL'] = 'ERNIE-Lite-8K'

SDK = qianfan.ChatCompletion()

def runModel(userPrompt: str, systemPrompt: str = '') -> str:
    '''调用文心一言回答问题。
    userPrompt: 用户的问题。
    systemPrompt: 系统提示。可选，默认空字符串。
    return: 回答。
    '''

    resp = SDK.do(
        model=os.environ['BAIDU_MODEL'],
        messages=[{'role':'user','content':userPrompt}],
        system=systemPrompt,
        stream=True
    )
    result_str = ''
    for r in resp:
        print(r['result'], end='', flush=True)
        result_str += str(r['result'])
    print('\n')
    return result_str

if '__main__' == __name__:

#     systemPrompt = '''你需要通过思维链来回复提问，采取以下步骤：
# 理解问题：系统首先需要理解用户提出的问题，识别问题的关键词和核心内容。
# 分解问题：将问题分解为若干个子问题或步骤，以便更好地理解和处理。
# 搜索信息：根据分解后的问题，系统需要在内部知识库或外部资源中搜索相关信息。
# 构建思维链：根据搜索到的信息，系统构建一个逻辑清晰的思维链，即按照一定的顺序和步骤来思考和回答问题。
# 生成回答：根据思维链，系统生成回答，并确保回答具有逻辑性和条理性。
# 进行验证以确保准确性。通过这一系列过程，最后你再总结答案回复。'''

    systemPrompt = '''当用户需要解决问题时，你需要按照以下步骤来处理：\
首先，你需要先识别出用户提出的问题或任务是什么。这是开始解决任何问题的第一步，\
只有明确知道用户所面对的问题或需要完成的任务，才能进行有效的分析并寻找解决方案。\
接下来，将问题或任务分解为其基本组成部分。\
这是将复杂问题或任务拆解成若干个简单的子问题或部分的过程。你可以通过找出问题中的关键要素和环节，\
理解它们之间的关系，以及它们在整体问题中的作用，来达到这一目的。\
然后，在每个子问题或组件之间建立逻辑连接和推理。这需要你运用已有的知识和经验，\
通过分析、比较、推理等方法，探讨各个子问题或组件之间的联系和影响。在这个过程中，\
你需要不断反思中间的结论，如果发现逻辑不严密或者推理有误，需要及时调整和修正。\
最后，将所有子问题的解决方案和推理结果整合起来，形成一个完整、连贯的最终答案或解决方案。\
这一步需要你综合所有信息和推理结果，考虑到每个子问题的重性和它们之间的相互关系，\
以确保最终答案的准确性和完整性。'''

    parser = argparse.ArgumentParser(description='文心一言')
    parser.add_argument('-p', type=str, help='问题', default='编写Python代码寻找函数 x^3+2*x-10 在区间 [0,100] 的最大值并输出对应的横坐标 x')
    parser.add_argument('-f', type=str, help='提示词文件路径', default='')
    args = parser.parse_args()
    if '' != args.f:
        with open(args.f, 'r', encoding='utf-8') as f:
            question = f.read()
    else:
        question = args.p

    runModel(question, systemPrompt)
