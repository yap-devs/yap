<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: proxy/socks/config.proto

namespace V2ray\Core\Proxy\Socks;

use UnexpectedValueException;

/**
 * AuthType is the authentication type of Socks proxy.
 *
 * Protobuf type <code>v2ray.core.proxy.socks.AuthType</code>
 */
class AuthType
{
    /**
     * NO_AUTH is for anonymous authentication.
     *
     * Generated from protobuf enum <code>NO_AUTH = 0;</code>
     */
    const NO_AUTH = 0;
    /**
     * PASSWORD is for username/password authentication.
     *
     * Generated from protobuf enum <code>PASSWORD = 1;</code>
     */
    const PASSWORD = 1;

    private static $valueToName = [
        self::NO_AUTH => 'NO_AUTH',
        self::PASSWORD => 'PASSWORD',
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

