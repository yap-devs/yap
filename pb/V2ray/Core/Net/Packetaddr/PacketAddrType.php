<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: common/net/packetaddr/config.proto

namespace V2ray\Core\Net\Packetaddr;

use UnexpectedValueException;

/**
 * Protobuf type <code>v2ray.core.net.packetaddr.PacketAddrType</code>
 */
class PacketAddrType
{
    /**
     * Generated from protobuf enum <code>None = 0;</code>
     */
    const None = 0;
    /**
     * Generated from protobuf enum <code>Packet = 1;</code>
     */
    const Packet = 1;

    private static $valueToName = [
        self::None => 'None',
        self::Packet => 'Packet',
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

