<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/vmess/inbound/config.proto

namespace V2ray\Core\Proxy\Vmess\Inbound;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.proxy.vmess.inbound.DetourConfig</code>
 */
class DetourConfig extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>string to = 1;</code>
     */
    protected $to = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $to
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Proxy\Vmess\Inbound\Config::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>string to = 1;</code>
     * @return string
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * Generated from protobuf field <code>string to = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setTo($var)
    {
        GPBUtil::checkString($var, True);
        $this->to = $var;

        return $this;
    }

}

