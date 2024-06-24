<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: transport/internet/request/roundtripper/httprt/config.proto

namespace V2ray\Core\Transport\Internet\Request\Roundtripper\Httprt;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.transport.internet.request.roundtripper.httprt.ClientConfig</code>
 */
class ClientConfig extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.v2ray.core.transport.internet.request.roundtripper.httprt.HTTPConfig http = 1;</code>
     */
    protected $http = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \V2ray\Core\Transport\Internet\Request\Roundtripper\Httprt\HTTPConfig $http
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Transport\Internet\Request\Roundtripper\Httprt\Config::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.transport.internet.request.roundtripper.httprt.HTTPConfig http = 1;</code>
     * @return \V2ray\Core\Transport\Internet\Request\Roundtripper\Httprt\HTTPConfig|null
     */
    public function getHttp()
    {
        return $this->http;
    }

    public function hasHttp()
    {
        return isset($this->http);
    }

    public function clearHttp()
    {
        unset($this->http);
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.transport.internet.request.roundtripper.httprt.HTTPConfig http = 1;</code>
     * @param \V2ray\Core\Transport\Internet\Request\Roundtripper\Httprt\HTTPConfig $var
     * @return $this
     */
    public function setHttp($var)
    {
        GPBUtil::checkMessage($var, \V2ray\Core\Transport\Internet\Request\Roundtripper\Httprt\HTTPConfig::class);
        $this->http = $var;

        return $this;
    }

}

