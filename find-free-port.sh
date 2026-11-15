#!/bin/bash
for port in 18080 18081 18082 18083 18084 18085 19080 19081 19082 20080; do
    if ! (echo > /dev/tcp/127.0.0.1/$port) 2>/dev/null; then
        echo "✅ پورت آزاد: $port"
        exit 0
    fi
done
echo "❌ پورت آزادی پیدا نشد"
