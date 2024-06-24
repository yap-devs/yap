<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: app/dns/config.proto

namespace V2ray\Core\App\Dns;

use UnexpectedValueException;

/**
 * Protobuf type <code>v2ray.core.app.dns.FallbackStrategy</code>
 */
class FallbackStrategy
{
    /**
     * Generated from protobuf enum <code>Enabled = 0;</code>
     */
    const Enabled = 0;
    /**
     * Generated from protobuf enum <code>Disabled = 1;</code>
     */
    const Disabled = 1;
    /**
     * Generated from protobuf enum <code>DisabledIfAnyMatch = 2;</code>
     */
    const DisabledIfAnyMatch = 2;

    private static $valueToName = [
        self::Enabled => 'Enabled',
        self::Disabled => 'Disabled',
        self::DisabledIfAnyMatch => 'DisabledIfAnyMatch',
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

