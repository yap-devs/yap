<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/freedom/config.proto

namespace GPBMetadata\Proxy\Freedom;

class Config
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Common\Protocol\ServerSpec::initOnce();
        \GPBMetadata\Common\Protoext\Extensions::initOnce();
        $pool->internalAddGeneratedFile(
            "\x0A\xD9\x04\x0A\x1Aproxy/freedom/config.proto\x12\x18v2ray.core.proxy.freedom\x1A common/protoext/extensions.proto\"Q\x0A\x13DestinationOverride\x12:\x0A\x06server\x18\x01 \x01(\x0B2*.v2ray.core.common.protocol.ServerEndpoint\"\x8B\x02\x0A\x06Config\x12H\x0A\x0Fdomain_strategy\x18\x01 \x01(\x0E2/.v2ray.core.proxy.freedom.Config.DomainStrategy\x12\x13\x0A\x07timeout\x18\x02 \x01(\x0DB\x02\x18\x01\x12K\x0A\x14destination_override\x18\x03 \x01(\x0B2-.v2ray.core.proxy.freedom.DestinationOverride\x12\x12\x0A\x0Auser_level\x18\x04 \x01(\x0D\"A\x0A\x0EDomainStrategy\x12\x09\x0A\x05AS_IS\x10\x00\x12\x0A\x0A\x06USE_IP\x10\x01\x12\x0B\x0A\x07USE_IP4\x10\x02\x12\x0B\x0A\x07USE_IP6\x10\x03\"+\x0A\x10SimplifiedConfig:\x17\x82\xB5\x18\x13\x0A\x08outbound\x12\x07freedomBi\x0A\x1Ccom.v2ray.core.proxy.freedomP\x01Z,github.com/v2fly/v2ray-core/v5/proxy/freedom\xAA\x02\x18V2Ray.Core.Proxy.Freedomb\x06proto3"
        , true);

        static::$is_initialized = true;
    }
}

