<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: common/protocol/user.proto

namespace GPBMetadata\Common\Protocol;

class User
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Google\Protobuf\Any::initOnce();
        $pool->internalAddGeneratedFile(
            "\x0A\xFE\x01\x0A\x1Acommon/protocol/user.proto\x12\x1Av2ray.core.common.protocol\"K\x0A\x04User\x12\x0D\x0A\x05level\x18\x01 \x01(\x0D\x12\x0D\x0A\x05email\x18\x02 \x01(\x09\x12%\x0A\x07account\x18\x03 \x01(\x0B2\x14.google.protobuf.AnyBo\x0A\x1Ecom.v2ray.core.common.protocolP\x01Z.github.com/v2fly/v2ray-core/v5/common/protocol\xAA\x02\x1AV2Ray.Core.Common.Protocolb\x06proto3"
        , true);

        static::$is_initialized = true;
    }
}

