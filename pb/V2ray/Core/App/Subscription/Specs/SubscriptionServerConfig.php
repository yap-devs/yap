<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: app/subscription/specs/abstract_spec.proto

namespace V2ray\Core\App\Subscription\Specs;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.app.subscription.specs.SubscriptionServerConfig</code>
 */
class SubscriptionServerConfig extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>string id = 1;</code>
     */
    protected $id = '';
    /**
     * Generated from protobuf field <code>map<string, string> metadata = 2;</code>
     */
    private $metadata;
    /**
     * Generated from protobuf field <code>.v2ray.core.app.subscription.specs.ServerConfiguration configuration = 3;</code>
     */
    protected $configuration = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $id
     *     @type array|\Google\Protobuf\Internal\MapField $metadata
     *     @type \V2ray\Core\App\Subscription\Specs\ServerConfiguration $configuration
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\App\Subscription\Specs\AbstractSpec::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>string id = 1;</code>
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Generated from protobuf field <code>string id = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setId($var)
    {
        GPBUtil::checkString($var, True);
        $this->id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>map<string, string> metadata = 2;</code>
     * @return \Google\Protobuf\Internal\MapField
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Generated from protobuf field <code>map<string, string> metadata = 2;</code>
     * @param array|\Google\Protobuf\Internal\MapField $var
     * @return $this
     */
    public function setMetadata($var)
    {
        $arr = GPBUtil::checkMapField($var, \Google\Protobuf\Internal\GPBType::STRING, \Google\Protobuf\Internal\GPBType::STRING);
        $this->metadata = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.app.subscription.specs.ServerConfiguration configuration = 3;</code>
     * @return \V2ray\Core\App\Subscription\Specs\ServerConfiguration|null
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function hasConfiguration()
    {
        return isset($this->configuration);
    }

    public function clearConfiguration()
    {
        unset($this->configuration);
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.app.subscription.specs.ServerConfiguration configuration = 3;</code>
     * @param \V2ray\Core\App\Subscription\Specs\ServerConfiguration $var
     * @return $this
     */
    public function setConfiguration($var)
    {
        GPBUtil::checkMessage($var, \V2ray\Core\App\Subscription\Specs\ServerConfiguration::class);
        $this->configuration = $var;

        return $this;
    }

}

