#!/usr/bin/env bash

set -e

TARGET_NAME=${1:-"HK.Contract.Basic.M1"}

res=$(curl -s --cookie 'lang=' 'https://app.alice.ws/api/product/product?action=regionToproduct&id=1')
stock=$(echo $res | jq -r ".[] | select(.name == \"$TARGET_NAME\") | .stock")

if [ $stock -gt 0 ]; then
    printf "\033[0;32m%(%Y-%m-%d %H:%M:%S)T %s: in stock => %d\033[0m\n" -1 $TARGET_NAME $stock
else
    printf "\033[0;31m%(%Y-%m-%d %H:%M:%S)T %s: out of stock\033[0m\n" -1 $TARGET_NAME
fi
