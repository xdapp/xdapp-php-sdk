<?php

namespace XDApp\ServiceReg;

class Context extends \stdClass {

    /**
     * 用户数据
     *
     * @var \stdClass
     */
    public $userdata;

    /**
     * 自定义的RPC请求的上下文内容
     *
     * @var string
     */
    public $headerAndContext;

    /**
     * 返回的RPC数据参数，例：
     *
     * ```php
     * $receiveParam = [
     *      'Flag'          => 0,
     *      'Ver'           => 1,
     *      'Length'        => 124,
     *      'AppId'         => 1,
     *      'ServiceId'     => 1,
     *      'RequestId'     => 1,
     *      'AdminId'       => 1,
     *      'ContextLength' => 0,
     * ];
     * ```
     *
     * @var array
     */
    public $receiveParam;

    /**
     * 请求的RequestId，同 `$this->receiveParam['RequestId']`
     *
     * @var int
     */
    public $requestId;

    /**
     * 请求的AdminId，同 `$this->receiveParam['AdminId']`
     *
     * @var int
     */
    public $adminId;

    /**
     * 请求的应用Id，同 `$this->receiveParam['AppId']`
     *
     * @var int
     */
    public $appId;

    /**
     * 请求的服务ID，同 `$this->receiveParam['ServiceId']`
     *
     * @var int
     */
    public $serviceId;

    /**
     * 请求的服务实例化对象
     *
     * @var Service
     */
    public $service;
}