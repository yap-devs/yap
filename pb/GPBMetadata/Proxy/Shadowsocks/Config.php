<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/shadowsocks/config.proto

namespace GPBMetadata\Proxy\Shadowsocks;

class Config
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Common\Net\Network::initOnce();
        \GPBMetadata\Common\Protocol\User::initOnce();
        \GPBMetadata\Common\Protocol\ServerSpec::initOnce();
        \GPBMetadata\Common\Net\Packetaddr\Config::initOnce();
        $pool->internalAddGeneratedFile(
            "\x0A\xB6\x06\x0A\x1Eproxy/shadowsocks/config.proto\x12\x1Cv2ray.core.proxy.shadowsocks\x1A\x1Acommon/protocol/user.proto\x1A!common/protocol/server_spec.proto\x1A\"common/net/packetaddr/config.proto\"\x9A\x01\x0A\x07Account\x12\x10\x0A\x08password\x18\x01 \x01(\x09\x12=\x0A\x0Bcipher_type\x18\x02 \x01(\x0E2(.v2ray.core.proxy.shadowsocks.CipherType\x12\x10\x0A\x08iv_check\x18\x03 \x01(\x08\x12,\x0A\"experiment_reduced_iv_head_entropy\x18\x91\xBF\x05 \x01(\x08\"\xCC\x01\x0A\x0CServerConfig\x12\x17\x0A\x0Budp_enabled\x18\x01 \x01(\x08B\x02\x18\x01\x12.\x0A\x04user\x18\x02 \x01(\x0B2 .v2ray.core.common.protocol.User\x12/\x0A\x07network\x18\x03 \x03(\x0E2\x1E.v2ray.core.common.net.Network\x12B\x0A\x0Fpacket_encoding\x18\x04 \x01(\x0E2).v2ray.core.net.packetaddr.PacketAddrType\"J\x0A\x0CClientConfig\x12:\x0A\x06server\x18\x01 \x03(\x0B2*.v2ray.core.common.protocol.ServerEndpoint*\\\x0A\x0ACipherType\x12\x0B\x0A\x07UNKNOWN\x10\x00\x12\x0F\x0A\x0BAES_128_GCM\x10\x01\x12\x0F\x0A\x0BAES_256_GCM\x10\x02\x12\x15\x0A\x11CHACHA20_POLY1305\x10\x03\x12\x08\x0A\x04NONE\x10\x04Bu\x0A com.v2ray.core.proxy.shadowsocksP\x01Z0github.com/v2fly/v2ray-core/v5/proxy/shadowsocks\xAA\x02\x1CV2Ray.Core.Proxy.Shadowsocksb\x06proto3"
        , true);

        static::$is_initialized = true;
    }
}

