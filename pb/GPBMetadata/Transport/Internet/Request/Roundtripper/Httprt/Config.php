<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: transport/internet/request/roundtripper/httprt/config.proto

namespace GPBMetadata\Transport\Internet\Request\Roundtripper\Httprt;

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
            "\x0A\xD5\x05\x0A;transport/internet/request/roundtripper/httprt/config.proto\x129v2ray.core.transport.internet.request.roundtripper.httprt\"\x98\x01\x0A\x0CClientConfig\x12S\x0A\x04http\x18\x01 \x01(\x0B2E.v2ray.core.transport.internet.request.roundtripper.httprt.HTTPConfig:3\x82\xB5\x18/\x0A%transport.request.roundtripper.client\x12\x06httprt\"\xB9\x01\x0A\x0CServerConfig\x12S\x0A\x04http\x18\x01 \x01(\x0B2E.v2ray.core.transport.internet.request.roundtripper.httprt.HTTPConfig\x12\x1F\x0A\x17no_decoding_session_tag\x18\x02 \x01(\x08:3\x82\xB5\x18/\x0A%transport.request.roundtripper.server\x12\x06httprt\"-\x0A\x0AHTTPConfig\x12\x0C\x0A\x04path\x18\x01 \x01(\x09\x12\x11\x0A\x09urlPrefix\x18\x02 \x01(\x09B\xCC\x01\x0A=com.v2ray.core.transport.internet.request.roundtripper.httprtP\x01ZMgithub.com/v2fly/v2ray-core/v5/transport/internet/request/roundtripper/httprt\xAA\x029V2Ray.Core.Transport.Internet.Request.Roundtripper.httprtb\x06proto3"
        , true);

        static::$is_initialized = true;
    }
}

