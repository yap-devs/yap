<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: common/net/port.proto

namespace GPBMetadata\Common\Net;

class Port
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        $pool->internalAddGeneratedFile(
            "\x0A\xFC\x01\x0A\x15common/net/port.proto\x12\x15v2ray.core.common.net\"%\x0A\x09PortRange\x12\x0C\x0A\x04From\x18\x01 \x01(\x0D\x12\x0A\x0A\x02To\x18\x02 \x01(\x0D\";\x0A\x08PortList\x12/\x0A\x05range\x18\x01 \x03(\x0B2 .v2ray.core.common.net.PortRangeB`\x0A\x19com.v2ray.core.common.netP\x01Z)github.com/v2fly/v2ray-core/v5/common/net\xAA\x02\x15V2Ray.Core.Common.Netb\x06proto3"
        , true);

        static::$is_initialized = true;
    }
}

