<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/vlite/outbound/config.proto

namespace V2ray\Core\Proxy\Vlite\Outbound;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.proxy.vlite.outbound.UDPProtocolConfig</code>
 */
class UDPProtocolConfig extends \Google\Protobuf\Internal\Message
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
     * Generated from protobuf field <code>string password = 3;</code>
     */
    protected $password = '';
    /**
     * Generated from protobuf field <code>bool scramble_packet = 4;</code>
     */
    protected $scramble_packet = false;
    /**
     * Generated from protobuf field <code>bool enable_fec = 5;</code>
     */
    protected $enable_fec = false;
    /**
     * Generated from protobuf field <code>bool enable_stabilization = 6;</code>
     */
    protected $enable_stabilization = false;
    /**
     * Generated from protobuf field <code>bool enable_renegotiation = 7;</code>
     */
    protected $enable_renegotiation = false;
    /**
     * Generated from protobuf field <code>uint32 handshake_masking_padding_size = 8;</code>
     */
    protected $handshake_masking_padding_size = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \V2ray\Core\Common\Net\IPOrDomain $address
     *     @type int $port
     *     @type string $password
     *     @type bool $scramble_packet
     *     @type bool $enable_fec
     *     @type bool $enable_stabilization
     *     @type bool $enable_renegotiation
     *     @type int $handshake_masking_padding_size
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Proxy\Vlite\Outbound\Config::initOnce();
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
     * Generated from protobuf field <code>string password = 3;</code>
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Generated from protobuf field <code>string password = 3;</code>
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
     * Generated from protobuf field <code>bool scramble_packet = 4;</code>
     * @return bool
     */
    public function getScramblePacket()
    {
        return $this->scramble_packet;
    }

    /**
     * Generated from protobuf field <code>bool scramble_packet = 4;</code>
     * @param bool $var
     * @return $this
     */
    public function setScramblePacket($var)
    {
        GPBUtil::checkBool($var);
        $this->scramble_packet = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool enable_fec = 5;</code>
     * @return bool
     */
    public function getEnableFec()
    {
        return $this->enable_fec;
    }

    /**
     * Generated from protobuf field <code>bool enable_fec = 5;</code>
     * @param bool $var
     * @return $this
     */
    public function setEnableFec($var)
    {
        GPBUtil::checkBool($var);
        $this->enable_fec = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool enable_stabilization = 6;</code>
     * @return bool
     */
    public function getEnableStabilization()
    {
        return $this->enable_stabilization;
    }

    /**
     * Generated from protobuf field <code>bool enable_stabilization = 6;</code>
     * @param bool $var
     * @return $this
     */
    public function setEnableStabilization($var)
    {
        GPBUtil::checkBool($var);
        $this->enable_stabilization = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bool enable_renegotiation = 7;</code>
     * @return bool
     */
    public function getEnableRenegotiation()
    {
        return $this->enable_renegotiation;
    }

    /**
     * Generated from protobuf field <code>bool enable_renegotiation = 7;</code>
     * @param bool $var
     * @return $this
     */
    public function setEnableRenegotiation($var)
    {
        GPBUtil::checkBool($var);
        $this->enable_renegotiation = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint32 handshake_masking_padding_size = 8;</code>
     * @return int
     */
    public function getHandshakeMaskingPaddingSize()
    {
        return $this->handshake_masking_padding_size;
    }

    /**
     * Generated from protobuf field <code>uint32 handshake_masking_padding_size = 8;</code>
     * @param int $var
     * @return $this
     */
    public function setHandshakeMaskingPaddingSize($var)
    {
        GPBUtil::checkUint32($var);
        $this->handshake_masking_padding_size = $var;

        return $this;
    }

}

