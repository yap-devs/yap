<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/loopback/config.proto

namespace GPBMetadata\Proxy\Loopback;

class Config
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Common\Protoext\Extensions::initOnce();
        $pool->internalAddGeneratedFile(
            "\x0A\xE7\x01\x0A\x1Bproxy/loopback/config.proto\x12\x19v2ray.core.proxy.loopback\"7\x0A\x06Config\x12\x13\x0A\x0Binbound_tag\x18\x01 \x01(\x09:\x18\x82\xB5\x18\x14\x0A\x08outbound\x12\x08loopbackBl\x0A\x1Dcom.v2ray.core.proxy.loopbackP\x01Z-github.com/v2fly/v2ray-core/v5/proxy/loopback\xAA\x02\x19V2Ray.Core.Proxy.Loopbackb\x06proto3"
        , true);

        static::$is_initialized = true;
    }
}

