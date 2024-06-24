<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: common/protocol/headers.proto

namespace V2ray\Core\Common\Protocol;

use UnexpectedValueException;

/**
 * Protobuf type <code>v2ray.core.common.protocol.SecurityType</code>
 */
class SecurityType
{
    /**
     * Generated from protobuf enum <code>UNKNOWN = 0;</code>
     */
    const UNKNOWN = 0;
    /**
     * Generated from protobuf enum <code>LEGACY = 1;</code>
     */
    const LEGACY = 1;
    /**
     * Generated from protobuf enum <code>AUTO = 2;</code>
     */
    const AUTO = 2;
    /**
     * Generated from protobuf enum <code>AES128_GCM = 3;</code>
     */
    const AES128_GCM = 3;
    /**
     * Generated from protobuf enum <code>CHACHA20_POLY1305 = 4;</code>
     */
    const CHACHA20_POLY1305 = 4;
    /**
     * Generated from protobuf enum <code>NONE = 5;</code>
     */
    const NONE = 5;
    /**
     * Generated from protobuf enum <code>ZERO = 6;</code>
     */
    const ZERO = 6;

    private static $valueToName = [
        self::UNKNOWN => 'UNKNOWN',
        self::LEGACY => 'LEGACY',
        self::AUTO => 'AUTO',
        self::AES128_GCM => 'AES128_GCM',
        self::CHACHA20_POLY1305 => 'CHACHA20_POLY1305',
        self::NONE => 'NONE',
        self::ZERO => 'ZERO',
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

