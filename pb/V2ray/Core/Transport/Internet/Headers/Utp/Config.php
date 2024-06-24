<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: transport/internet/headers/utp/config.proto

namespace V2ray\Core\Transport\Internet\Headers\Utp;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.transport.internet.headers.utp.Config</code>
 */
class Config extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>uint32 version = 1;</code>
     */
    protected $version = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int $version
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Transport\Internet\Headers\Utp\Config::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>uint32 version = 1;</code>
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Generated from protobuf field <code>uint32 version = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setVersion($var)
    {
        GPBUtil::checkUint32($var);
        $this->version = $var;

        return $this;
    }

}

