<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: app/observatory/command/command.proto

namespace V2ray\Core\App\Observatory\Command;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.app.observatory.command.GetOutboundStatusResponse</code>
 */
class GetOutboundStatusResponse extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.v2ray.core.app.observatory.ObservationResult status = 1;</code>
     */
    protected $status = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \V2ray\Core\App\Observatory\ObservationResult $status
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\App\Observatory\Command\Command::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.app.observatory.ObservationResult status = 1;</code>
     * @return \V2ray\Core\App\Observatory\ObservationResult|null
     */
    public function getStatus()
    {
        return $this->status;
    }

    public function hasStatus()
    {
        return isset($this->status);
    }

    public function clearStatus()
    {
        unset($this->status);
    }

    /**
     * Generated from protobuf field <code>.v2ray.core.app.observatory.ObservationResult status = 1;</code>
     * @param \V2ray\Core\App\Observatory\ObservationResult $var
     * @return $this
     */
    public function setStatus($var)
    {
        GPBUtil::checkMessage($var, \V2ray\Core\App\Observatory\ObservationResult::class);
        $this->status = $var;

        return $this;
    }

}

