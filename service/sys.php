<?php
namespace XDApp\ServiceReg;

return new class {
    /**
     * 注册服务，在连接到 console 微服务系统后，会收到一个 sys_reg() 的rpc回调
     *
     * @param int $time
     * @param string $rand
     * @param string $hash
     * @param bool $extend
     * @return array
     */
    public function reg($time, $rand, $hash, $extend = false) {
        $context = Service::getCurrentContext();
        if (!$context) {
            Service::warn($err = "[warn] sys_reg()注册服务，获取上下文失败，请联系管理员");
            return [
                'status' => false,
                'err' => $err,
            ];
        }

        /**
         * @var Service $service
         */
        $service = $context->service;
        if ($service->isRegSuccess()) {
            Service::warn($err = "[warn] sys_reg()注册服务，已注册成功");
            return [
                'status' => false,
                'err'    => $err
            ];
        }
        if ($hash !== sha1("$time.$rand.xdapp.com")) {
            # 验证失败
            Service::warn($err = "[warn] sys_reg()注册服务，hash验证失败");
            return [
                'status' => false,
                'err'    => $err
            ];
        }

        $now  = time();
        $diff = abs(time() - $time);
        if ($diff > 180) {
            # 超时
            Service::warn($err = "[warn] sys_reg()注册服务请求超时, 请检查服务器时间. 服务端时间戳: $time, 你的时间戳: $now, 差: $diff");
            return [
                'status' => false,
                'err'    => $err
            ];
        }
        $time = time();

        $rs = [
            'status'  => true,
            'app'     => $service->appName,
            'name'    => $service->serviceName,
            'time'    => $time,
            'rand'    => $rand,
            'version' => $service->getVersion(),
            'hash'    => $service->getRegHash($time, $rand),
        ];

        if ($extend) {
            $cli = new \Swoole\Coroutine\Http\Client('www.xdapp.com', 443, true);
            $cli->setHeaders([
                'Host'       => "www.xdapp.com",
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml',
            ]);
            $cli->set(['timeout' => 3]);
            $cli->get("/api/myip?appId={$service->appName}&time=$time&sign=". md5("{$service->appName}{$time}.xdapp.com"));
            //$cli->get("/api/myip?app={$service->appName}&time=$time&sign=". md5("{$time}.{$service->appName}.xdapp.com"));
            $code = $cli->statusCode;
            $ip = $cli->body;
            $cli->close();

            $rs['ip'] = $ip;
        }
        return $rs;
    }

    /**
     * 重启服务器
     */
    public function reload() {
        $context = Service::getCurrentContext();
        if (!$context)return false;
        if (!$context->service->isRegSuccess()) {
            return false;
        }

        $server = $context->service->getSwooleServer();
        if (!$server)return false;

        \Swoole\Timer::after(100, function() use ($server) {
            $server->reload(true);
        });

        return true;
    }

    public function regErr($msg, $data = []) {
        # 已经注册了
        $context = Service::getCurrentContext();
        if (!$context)return;

        if ($context->service->isRegSuccess()) {
            # 已注册成功
            return;
        }

        $context->service->closeByServer($msg, $data);
    }

    public function close($msg = 'Close by server', $data = []) {
        $context = Service::getCurrentContext();
        if (!$context)return false;

        $context->service->closeByServer($msg, $data);
        return true;
    }

    /**
     * 注册成功回调
     *
     * @param array $data 服务器返回的数据
     * @param string $time 时间戳
     * @param string $rand 随机字符串
     * @param string $hash 验证hash
     */
    public function regOk($data, $time, $rand, $hash) {
        $context = Service::getCurrentContext();
        if (!$context)return;

        if (strlen($rand) < 16) {
            return;
        }
        $service = $context->service;

        // 已经注册成功
        if ($service->isRegSuccess())return;

        if ($service->getRegHash($time, $rand) !== $hash) {
            // 断开连接
            $service->client->close();
            $service->warn("RPC服务注册失败，返回验证错误");
            return;
        }

        # 注册成功
        $service->setRegSuccess($data);
    }

    /**
     * 输出日志的RPC调用方法
     *
     * @param string $type
     * @param string $log
     * @param null|array $data
     */
    public function log($type, $log, $data = null) {
        $context = Service::getCurrentContext();
        if (!$context)return;
        $service = $context->service;
        if (!$service->isRegSuccess()) {
            return;
        }

        switch ($type) {
            case 'debug':
                $service->debug($log, $data);
                break;

            case 'warn':
                $service->warn($log, $data);
                break;

            case 'info':
                $service->info($log, $data);
                break;

            default:
                $service->log($log, $data);
                break;
        }
    }

    public function ping() {
        return true;
    }

    /**
     * 返回所有注册的名称
     *
     * @return array
     */
    public function getFunctions() {
        $context = Service::getCurrentContext();
        if (!$context)return [];
        $service = $context->service;

        if (!$service->isRegSuccess()) {
            return [];
        }
        return $service->getNames();
    }
};