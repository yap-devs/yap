<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/shadowsocks/simplified/config.proto

namespace V2ray\Core\Proxy\Shadowsocks\Simplified;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.proxy.shadowsocks.simplified.ClientConfig</code>
 */
class ClientConfig extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.v2ray.core.common.net.IPOrDomain address = 1;</code>
     */
    protected $address = null;
    /**
     * Generated from protobuf field <code>uint32 port = 2;</code>
     */
    protected $port = 0;
    /**
     * Generated from protobuf field <code>.v2ray.core.proxy.shadowsocks.simplified.CipherTypeWrapper method = 3;</code>
     */
    protected $method = null;
    /**
     * Generated from protobuf field <code>string password = 4;</code>
     */
    protected $password = '';
    /**
     * Generated from protobuf field <code>bool experiment_reduced_iv_head_entropy = 90001;</code>
     */
    protected $experiment_reduced_iv_head_entropy = false;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \V2ray\Core\Common\Net\IPOrDomain $address
     *     @type int $port
     *     @type \V2ray\Core\Proxy\Shadowsocks\Simplified\CipherTypeWrapper $method
     *     @type string $password
     *     @type bool $experiment_reduced_iv_head_entropy
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Proxy\Shadowsocks\Simplified\Config::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.common.net.IPOrDomain address = 1;</code>
     * @return \V2ray\Core\Common\Net\IPOrDomain|null
     */
    public function getAddress()
    {
        return $this->address;
    }

    public function hasAddress()
    {
        return isset($this->address);
    }

    public function clearAddress()
    {
        unset($this->address);
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.common.net.IPOrDomain address = 1;</code>
     * @param \V2ray\Core\Common\Net\IPOrDomain $var
     * @return $this
     */
    public function setAddress($var)
    {
        GPBUtil::checkMessage($var, \V2ray\Core\Common\Net\IPOrDomain::class);
        $this->address = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint32 port = 2;</code>
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Generated from protobuf field <code>uint32 port = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setPort($var)
    {
        GPBUtil::checkUint32($var);
        $this->port = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.proxy.shadowsocks.simplified.CipherTypeWrapper method = 3;</code>
     * @return \V2ray\Core\Proxy\Shadowsocks\Simplified\CipherTypeWrapper|null
     */
    public function getMethod()
    {
        return $this->method;
    }

    public function hasMethod()
    {
        return isset($this->method);
    }

    public function clearMethod()
    {
        unset($this->method);
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.proxy.shadowsocks.simplified.CipherTypeWrapper method = 3;</code>
     * @param \V2ray\Core\Proxy\Shadowsocks\Simplified\CipherTypeWrapper $var
     * @return $this
     */
    public function setMethod($var)
    {
        GPBUtil::checkMessage($var, \V2ray\Core\Proxy\Shadowsocks\Simplified\CipherTypeWrapper::class);
        $this->method = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string password = 4;</code>
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Generated from protobuf field <code>string password = 4;</code>
     * @param string $var
     * @return $this
     */
    public function setPassword($var)
    {
        GPBUtil::checkString($var, True);
        $this->password = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool experiment_reduced_iv_head_entropy = 90001;</code>
     * @return bool
     */
    public function getExperimentReducedIvHeadEntropy()
    {
        return $this->experiment_reduced_iv_head_entropy;
    }

    /**
     * Generated from protobuf field <code>bool experiment_reduced_iv_head_entropy = 90001;</code>
     * @param bool $var
     * @return $this
     */
    public function setExperimentReducedIvHeadEntropy($var)
    {
        GPBUtil::checkBool($var);
        $this->experiment_reduced_iv_head_entropy = $var;

        return $this;
    }

}

