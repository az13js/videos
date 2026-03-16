import json
from typing import List
import os

def get_filter_list() -> List[str]:
    this_dir = os.path.dirname(__file__)
    config_dir = os.path.dirname(this_dir)
    config_file = os.path.join(config_dir, 'config.json')
    try:
        config = json.load(open(config_file, 'r', encoding='utf-8'))
    except Exception as e:
        print('请检查config.json文件是否存在，其内容是否正确！' + str(e))
        exit(1)
    return config

if __name__ == '__main__':
    print(get_filter_list())
