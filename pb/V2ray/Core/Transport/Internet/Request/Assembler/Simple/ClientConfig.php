<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: transport/internet/request/assembler/simple/config.proto

namespace V2ray\Core\Transport\Internet\Request\Assembler\Simple;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>v2ray.core.transport.internet.request.assembler.simple.ClientConfig</code>
 */
class ClientConfig extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>int32 max_write_size = 1;</code>
     */
    protected $max_write_size = 0;
    /**
     * Generated from protobuf field <code>int32 wait_subsequent_write_ms = 2;</code>
     */
    protected $wait_subsequent_write_ms = 0;
    /**
     * Generated from protobuf field <code>int32 initial_polling_interval_ms = 3;</code>
     */
    protected $initial_polling_interval_ms = 0;
    /**
     * Generated from protobuf field <code>int32 max_polling_interval_ms = 4;</code>
     */
    protected $max_polling_interval_ms = 0;
    /**
     * Generated from protobuf field <code>int32 min_polling_interval_ms = 5;</code>
     */
    protected $min_polling_interval_ms = 0;
    /**
     * Generated from protobuf field <code>float backoff_factor = 6;</code>
     */
    protected $backoff_factor = 0.0;
    /**
     * Generated from protobuf field <code>int32 failed_retry_interval_ms = 7;</code>
     */
    protected $failed_retry_interval_ms = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int $max_write_size
     *     @type int $wait_subsequent_write_ms
     *     @type int $initial_polling_interval_ms
     *     @type int $max_polling_interval_ms
     *     @type int $min_polling_interval_ms
     *     @type float $backoff_factor
     *     @type int $failed_retry_interval_ms
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Transport\Internet\Request\Assembler\Simple\Config::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>int32 max_write_size = 1;</code>
     * @return int
     */
    public function getMaxWriteSize()
    {
        return $this->max_write_size;
    }

    /**
     * Generated from protobuf field <code>int32 max_write_size = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setMaxWriteSize($var)
    {
        GPBUtil::checkInt32($var);
        $this->max_write_size = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 wait_subsequent_write_ms = 2;</code>
     * @return int
     */
    public function getWaitSubsequentWriteMs()
    {
        return $this->wait_subsequent_write_ms;
    }

    /**
     * Generated from protobuf field <code>int32 wait_subsequent_write_ms = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setWaitSubsequentWriteMs($var)
    {
        GPBUtil::checkInt32($var);
        $this->wait_subsequent_write_ms = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 initial_polling_interval_ms = 3;</code>
     * @return int
     */
    public function getInitialPollingIntervalMs()
    {
        return $this->initial_polling_interval_ms;
    }

    /**
     * Generated from protobuf field <code>int32 initial_polling_interval_ms = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setInitialPollingIntervalMs($var)
    {
        GPBUtil::checkInt32($var);
        $this->initial_polling_interval_ms = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 max_polling_interval_ms = 4;</code>
     * @return int
     */
    public function getMaxPollingIntervalMs()
    {
        return $this->max_polling_interval_ms;
    }

    /**
     * Generated from protobuf field <code>int32 max_polling_interval_ms = 4;</code>
     * @param int $var
     * @return $this
     */
    public function setMaxPollingIntervalMs($var)
    {
        GPBUtil::checkInt32($var);
        $this->max_polling_interval_ms = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 min_polling_interval_ms = 5;</code>
     * @return int
     */
    public function getMinPollingIntervalMs()
    {
        return $this->min_polling_interval_ms;
    }

    /**
     * Generated from protobuf field <code>int32 min_polling_interval_ms = 5;</code>
     * @param int $var
     * @return $this
     */
    public function setMinPollingIntervalMs($var)
    {
        GPBUtil::checkInt32($var);
        $this->min_polling_interval_ms = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>float backoff_factor = 6;</code>
     * @return float
     */
    public function getBackoffFactor()
    {
        return $this->backoff_factor;
    }

    /**
     * Generated from protobuf field <code>float backoff_factor = 6;</code>
     * @param float $var
     * @return $this
     */
    public function setBackoffFactor($var)
    {
        GPBUtil::checkFloat($var);
        $this->backoff_factor = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 failed_retry_interval_ms = 7;</code>
     * @return int
     */
    public function getFailedRetryIntervalMs()
    {
        return $this->failed_retry_interval_ms;
    }

    /**
     * Generated from protobuf field <code>int32 failed_retry_interval_ms = 7;</code>
     * @param int $var
     * @return $this
     */
    public function setFailedRetryIntervalMs($var)
    {
        GPBUtil::checkInt32($var);
        $this->failed_retry_interval_ms = $var;

        return $this;
    }

}

