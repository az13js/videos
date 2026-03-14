# 工具脚本

1. 参考baidu.py中的注释，安装千帆SDK。
2. 需要去百度智能云创建调用大模型的密钥。
3. 修改baidu.py或者设置环境变量，选择合适的模型。本地我用的是“ERNIE-Lite-8K”，免费而且性能够用。
4. 修改`bililike.sh`中的路径。
5. `alias bililike='bash /opt/python_tools/baidu_ai/bililike.sh'`（路径改成你自己的）在解压缩后目录执行`bililike`。
6. 如果期望用别的模型，如OpenAI或者其它的模型，封装一个`runModel`函数给`bililike.py`调用即可，不算麻烦。
