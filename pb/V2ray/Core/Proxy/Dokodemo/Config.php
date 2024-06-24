<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/dokodemo/config.proto

namespace V2ray\Core\Proxy\Dokodemo;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.proxy.dokodemo.Config</code>
 */
class Config extends \Google\Protobuf\Internal\Message
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
     * List of networks that the Dokodemo accepts.
     * Deprecated. Use networks.
     *
     * Generated from protobuf field <code>.v2ray.core.common.net.NetworkList network_list = 3 [deprecated = true];</code>
     * @deprecated
     */
    protected $network_list = null;
    /**
     * List of networks that the Dokodemo accepts.
     *
     * Generated from protobuf field <code>repeated .v2ray.core.common.net.Network networks = 7;</code>
     */
    private $networks;
    /**
     * Generated from protobuf field <code>uint32 timeout = 4 [deprecated = true];</code>
     * @deprecated
     */
    protected $timeout = 0;
    /**
     * Generated from protobuf field <code>bool follow_redirect = 5;</code>
     */
    protected $follow_redirect = false;
    /**
     * Generated from protobuf field <code>uint32 user_level = 6;</code>
     */
    protected $user_level = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \V2ray\Core\Common\Net\IPOrDomain $address
     *     @type int $port
     *     @type \V2ray\Core\Common\Net\NetworkList $network_list
     *           List of networks that the Dokodemo accepts.
     *           Deprecated. Use networks.
     *     @type array<int>|\Google\Protobuf\Internal\RepeatedField $networks
     *           List of networks that the Dokodemo accepts.
     *     @type int $timeout
     *     @type bool $follow_redirect
     *     @type int $user_level
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Proxy\Dokodemo\Config::initOnce();
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
     * List of networks that the Dokodemo accepts.
     * Deprecated. Use networks.
     *
     * Generated from protobuf field <code>.v2ray.core.common.net.NetworkList network_list = 3 [deprecated = true];</code>
     * @return \V2ray\Core\Common\Net\NetworkList|null
     * @deprecated
     */
    public function getNetworkList()
    {
        @trigger_error('network_list is deprecated.', E_USER_DEPRECATED);
        return $this->network_list;
    }

    public function hasNetworkList()
    {
        @trigger_error('network_list is deprecated.', E_USER_DEPRECATED);
        return isset($this->network_list);
    }

    public function clearNetworkList()
    {
        @trigger_error('network_list is deprecated.', E_USER_DEPRECATED);
        unset($this->network_list);
    }

    /**
     * List of networks that the Dokodemo accepts.
     * Deprecated. Use networks.
     *
     * Generated from protobuf field <code>.v2ray.core.common.net.NetworkList network_list = 3 [deprecated = true];</code>
     * @param \V2ray\Core\Common\Net\NetworkList $var
     * @return $this
     * @deprecated
     */
    public function setNetworkList($var)
    {
        @trigger_error('network_list is deprecated.', E_USER_DEPRECATED);
        GPBUtil::checkMessage($var, \V2ray\Core\Common\Net\NetworkList::class);
        $this->network_list = $var;

        return $this;
    }

    /**
     * List of networks that the Dokodemo accepts.
     *
     * Generated from protobuf field <code>repeated .v2ray.core.common.net.Network networks = 7;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getNetworks()
    {
        return $this->networks;
    }

    /**
     * List of networks that the Dokodemo accepts.
     *
     * Generated from protobuf field <code>repeated .v2ray.core.common.net.Network networks = 7;</code>
     * @param array<int>|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setNetworks($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::ENUM, \V2ray\Core\Common\Net\Network::class);
        $this->networks = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint32 timeout = 4 [deprecated = true];</code>
     * @return int
     * @deprecated
     */
    public function getTimeout()
    {
        @trigger_error('timeout is deprecated.', E_USER_DEPRECATED);
        return $this->timeout;
    }

    /**
     * Generated from protobuf field <code>uint32 timeout = 4 [deprecated = true];</code>
     * @param int $var
     * @return $this
     * @deprecated
     */
    public function setTimeout($var)
    {
        @trigger_error('timeout is deprecated.', E_USER_DEPRECATED);
        GPBUtil::checkUint32($var);
        $this->timeout = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool follow_redirect = 5;</code>
     * @return bool
     */
    public function getFollowRedirect()
    {
        return $this->follow_redirect;
    }

    /**
     * Generated from protobuf field <code>bool follow_redirect = 5;</code>
     * @param bool $var
     * @return $this
     */
    public function setFollowRedirect($var)
    {
        GPBUtil::checkBool($var);
        $this->follow_redirect = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint32 user_level = 6;</code>
     * @return int
     */
    public function getUserLevel()
    {
        return $this->user_level;
    }

    /**
     * Generated from protobuf field <code>uint32 user_level = 6;</code>
     * @param int $var
     * @return $this
     */
    public function setUserLevel($var)
    {
        GPBUtil::checkUint32($var);
        $this->user_level = $var;

        return $this;
    }

}

