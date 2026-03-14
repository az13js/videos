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
