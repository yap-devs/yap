<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: transport/internet/config.proto

namespace V2ray\Core\Transport\Internet;

use UnexpectedValueException;

/**
 * Protobuf type <code>v2ray.core.transport.internet.TransportProtocol</code>
 */
class TransportProtocol
{
    /**
     * Generated from protobuf enum <code>TCP = 0;</code>
     */
    const TCP = 0;
    /**
     * Generated from protobuf enum <code>UDP = 1;</code>
     */
    const UDP = 1;
    /**
     * Generated from protobuf enum <code>MKCP = 2;</code>
     */
    const MKCP = 2;
    /**
     * Generated from protobuf enum <code>WebSocket = 3;</code>
     */
    const WebSocket = 3;
    /**
     * Generated from protobuf enum <code>HTTP = 4;</code>
     */
    const HTTP = 4;
    /**
     * Generated from protobuf enum <code>DomainSocket = 5;</code>
     */
    const DomainSocket = 5;

    private static $valueToName = [
        self::TCP => 'TCP',
        self::UDP => 'UDP',
        self::MKCP => 'MKCP',
        self::WebSocket => 'WebSocket',
        self::HTTP => 'HTTP',
        self::DomainSocket => 'DomainSocket',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }


    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no value defined for name %s', __CLASS__, $name));
        }
        return constant($const);
    }
}

