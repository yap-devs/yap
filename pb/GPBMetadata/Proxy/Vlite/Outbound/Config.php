<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/vlite/outbound/config.proto

namespace GPBMetadata\Proxy\Vlite\Outbound;

class Config
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Common\Net\Address::initOnce();
        \GPBMetadata\Common\Protoext\Extensions::initOnce();
        $pool->internalAddGeneratedFile(
            "\x0A\x81\x04\x0A!proxy/vlite/outbound/config.proto\x12\x1Fv2ray.core.proxy.vlite.outbound\x1A common/protoext/extensions.proto\"\x90\x02\x0A\x11UDPProtocolConfig\x122\x0A\x07address\x18\x01 \x01(\x0B2!.v2ray.core.common.net.IPOrDomain\x12\x0C\x0A\x04port\x18\x02 \x01(\x0D\x12\x10\x0A\x08password\x18\x03 \x01(\x09\x12\x17\x0A\x0Fscramble_packet\x18\x04 \x01(\x08\x12\x12\x0A\x0Aenable_fec\x18\x05 \x01(\x08\x12\x1C\x0A\x14enable_stabilization\x18\x06 \x01(\x08\x12\x1C\x0A\x14enable_renegotiation\x18\x07 \x01(\x08\x12&\x0A\x1Ehandshake_masking_padding_size\x18\x08 \x01(\x0D:\x16\x82\xB5\x18\x12\x0A\x08outbound\x12\x06vliteuB~\x0A#com.v2ray.core.proxy.vlite.outboundP\x01Z3github.com/v2fly/v2ray-core/v5/proxy/vlite/outbound\xAA\x02\x1FV2Ray.Core.Proxy.Vlite.Outboundb\x06proto3"
        , true);

        static::$is_initialized = true;
    }
}

