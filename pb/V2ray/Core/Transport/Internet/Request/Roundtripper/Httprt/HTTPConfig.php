<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: transport/internet/request/roundtripper/httprt/config.proto

namespace V2ray\Core\Transport\Internet\Request\Roundtripper\Httprt;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.transport.internet.request.roundtripper.httprt.HTTPConfig</code>
 */
class HTTPConfig extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>string path = 1;</code>
     */
    protected $path = '';
    /**
     * Generated from protobuf field <code>string urlPrefix = 2;</code>
     */
    protected $urlPrefix = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $path
     *     @type string $urlPrefix
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Transport\Internet\Request\Roundtripper\Httprt\Config::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>string path = 1;</code>
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Generated from protobuf field <code>string path = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setPath($var)
    {
        GPBUtil::checkString($var, True);
        $this->path = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string urlPrefix = 2;</code>
     * @return string
     */
    public function getUrlPrefix()
    {
        return $this->urlPrefix;
    }

    /**
     * Generated from protobuf field <code>string urlPrefix = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setUrlPrefix($var)
    {
        GPBUtil::checkString($var, True);
        $this->urlPrefix = $var;

        return $this;
    }

}

