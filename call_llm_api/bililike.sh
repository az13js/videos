#!/bin/bash

work_dir="$(pwd)"
if [ -f "$work_dir/data.json" ];
then
    echo "data.json exists"
else
    echo "data.json not exists"
    exit 1
fi

cd /opt/python_tools/baidu_ai/
# source venv/bin/activate
python bililike.py -f "$work_dir" -o "$work_dir/like"
