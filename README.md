# XDApp RPC Service SDK

XDAppRPC服务SDK

## 依赖

* PHP >= 7.0（推荐7.2）
* PHP Swoole 扩展 >=4.0，推荐最新版本
* PHP hprose 扩展
* PHP mbstring 扩展 (通常默认安装)
* PHP snappy 扩展，安装 see https://github.com/kjdev/php-ext-snappy

## 安装

> 首先需要安装composer，安装方式（see https://getcomposer.org/download/）

### 在已有PHP项目中加入依赖包
```
composer require xdapp/service-register
```

### 在新项目中使用（HelloWorld，适合新手）

1.新建文件夹，创建 `composer.json` 文件，内容：

```json
{
  "minimum-stability": "stable",
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  },
  "require": {
    "xdapp/service-register": "^1.1"
  }
}
```

2.运行 `composer install`，稍等片刻即可安装好（若网络慢，可使用中国镜像，see https://pkg.phpcomposer.com/）

3.创建一个文件夹，比如叫 `service`，然后创建一个 `test.php` 文件，内容：
```php
<?php
return new class() {
   function hello() {
      return "hello world";
   }
};
```

4.创建 `run.php` 文件：
```php
<?php
use XDApp\ServiceReg\Service;
$service = Service::factory('demo', 'test', '123456');      // app、service、token
$service->addServiceByDir(__DIR__.'/service/');             // 注册 service 目录下所有匿名类
$service->connectToDev();  // 连到线上测试环境
```

5. 运行 `php run.php`

> 确保你的服务器时间准确并且线上环境需要需要支持SSL支持，执行 `php --ri swoole` 查看，有 openssl 表示ok，或执行 `php -a` 输入 `echo SWOOLE_SSL;` 有512数字表示OK

## Dockerfile

```dockerfile
FROM php:7.3-cli
RUN sed -ri "s/(httpredir|deb|security).debian.org/mirrors.aliyun.com/g" /etc/apt/sources.list
RUN apt-get update \
    && apt-get install -y libyaml-dev libssl-dev curl librdkafka-dev net-tools iputils-ping git

RUN docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql \
    && pecl install msgpack \
    && pecl install redis \
    && pecl install yaml \
    && pecl install hprose \
    && docker-php-ext-enable msgpack redis yaml hprose

RUN git clone --recursive --depth=1 https://github.com/kjdev/php-ext-snappy.git \
    && cd php-ext-snappy \
    && phpize && ./configure && make && make install \
    && docker-php-ext-enable snappy

# 可指定版本，例如 pecl install swoole-4.4.16
RUN printf "no\nyes\n" | pecl install swoole \
    && docker-php-ext-enable swoole \
#    && echo "swoole.use_shortname = 'Off'" >> /usr/local/etc/php/conf.d/docker-php-ext-swoole.ini

COPY . /usr/src/server
WORKDIR /usr/src/server
CMD [ "php", "./bin/server.php"]
```

## 更多使用方法

```php
<?php
// run.php
use XDApp\ServiceReg\Service;
// 其中 demo 为项目名(AppName)，test 为服务名(ServiceName)，123456 为密钥
$service = Service::factory('demo', 'test', '123456');

// 1. 注册一个前端页面可访问的abc()方法
$service->addWebFunction(function($arg) {
    var_dump($arg);
    return true;
}, 'abc');


// 2. 将一个文件夹的所有php文件注册进服务，支持子文件夹，php文件名为服务器名前缀，
// php内返回一个闭包 fuction 或一个匿名 class，返回的匿名class的所有方法名会被注册，参考 service/sys.php

// 单个方法推荐使用1，多个方法推荐2

// 3. 使用 addFunction 注册方法
// 请注意，只有服务名相同的前缀rpc方法才会被页面前端调用到
$service->addFunction(function($arg) {
    // 获取 context 对象
    $context = Service::getCurrentContext();
    var_dump($context->adminId);

    return true;
}, 'test_abc');

// 连接到生产环境（国内）
//$service->connectToProduce();

// 连接到生产环境（海外东南亚）
//$service->connectToProduceAsia();

// 连接到欧洲生产环境
//$service->connectToProduceEurope();

// 或 连接到本地测试开发服务器
//$service->connectToLocalDev('127.0.0.1', 8082);

// 或 连接到外网测试服务器
//$service->connectToDev();

// RPC调用远端方法，需要XDApp支持后可用
$list = $service->xdAppService()->my->getMenu();
// 等同如下：
// $list = $service->service()->xdapp->my->getMenu();
// print_r($list);
```

更多的使用方法see: [https://github.com/hprose/hprose-php/wiki/06-Hprose-服务器](https://github.com/hprose/hprose-php/wiki/06-Hprose-服务器)

### 服务环境

区域           | 环境      |  使用方法
--------------|----------|---------
国服           | 生产环境  | connectToProduce()
东南亚正式服     | 生产环境  | connectToProduceAsia()
欧洲正式服       | 生产环境  | connectToProduceEurope()
DEV测试环境     | 测试环境  | connectToDev()
本地测试环境     | 测试环境  | connectToLocalDev()

### 关于 `context` 上下文对象

在RPC请求时，如果需要获取到请求时的管理员ID等等参数，可以用此获取，如上面 `hello` 的例子，通过 `$context = Service::getCurrentContext()` 可获取到 `context`，包括：

参数         |   说明
------------|---------------------
service     | 当前服务
client      | 通信的连接对象，可以使用 `close()` 方法关闭连接
requestId   | 请求的ID
appId       | 请求的应用ID
serviceId   | 请求发起的服务ID，0表示XDApp系统请求，1表示来自浏览器的请求
adminId     | 请求的管理员ID，0表示系统请求
userdata    | 默认 stdClass 对象，可以自行设置参数

更多参数见 `XDApp\ServiceReg\Context` 对象


`Service` 常用方法如下：

### `connectToProduce()`

连接到生产环境，将会创建一个异步tls连接接受和发送RPC数据，无需自行暴露端口，如果遇到网络问题和服务器断开可以自动重连，除非是因为密钥等问题导致的断开将不会重新连接

### `connectToDev($serviceKey = null)`

同上，连接到研发环境, 不设置 serviceKey 则使用 new ServiceAgent 时传入的密钥

### `connectToLocalDev($host = '127.0.0.1', $port = 8061, $serviceKey = null)`

同上，连接到本地研发服务器，请下载 XDApp-Local-Dev 本地开发工具，https://github.com/xdapp/xdapp-local-dev ，启动服务

### `addWebFunction($function, $alias = null, $option = [])`

注册一个前端web页面可访问的RPC方法到服务上，它是 `service.addFunction()` 方法的封装，差别在于会自动对 `alias` 增加 `serviceName` 前缀

`$service->addWebFunction(hello, 'hello')` 相当于 `$service->addFunction(hello, 'servicename_hello')`

### `addFunction($function, $alias = null, $option = [])`

注册一个RPC方法到服务上


### `addHttpApiProxy($url, $alias = 'api', $methods = ['get'], array $httpHeaders = [])`

添加一个http代理

使用场景：
当服务器里提供一个内部Http接口，但是它没有暴露给外网也没有权限验证处理，但希望Web页面可以使用
此时可以使用此方法，将它暴露成为一个XDApp的RPC服务，在网页里直接通过RPC请求将数据转发到SDK请求后返回，不仅可以实现内网穿透功能还可以在Console后台设置访问权限。

每个Http代理请求都会带以下头信息，方便业务处理:

* X-Xdapp-Proxy: True
* X-Xdapp-App-Id: 1
* X-Xdapp-Service: demo
* X-Xdapp-Request-Id: 1
* X-Xdapp-Admin-Id: 1

```php
$this->addHttpApiProxy('http://127.0.0.1:9999', 'myApi', ['get', 'post', 'delete', 'put'])
```

Vue页面使用

方法接受3个参数，$uri, $data, $timeout，其中 $data 只有在 post 和 put 有效，$timeout 默认 30 秒

```javascript
// 其中gm为注册的服务名
this.$service.gm.myApi.get('/uri?a=arg1&b=arg2');
// 最终将会请求 http://127.0.0.1:9999/uri?a=arg1&b=arg2
// 返回对象 {code: 200, headers: {...}, body: '...'}

// 使用post, 第2个参数接受string或字符串, 第3个参数可设置超时
this.$service.gm.myApi.post('/uri?a=1', {a:'arg1', b:'arg2'}, 15);
```


### `addMissingFunction(function, $option = [])`

此方法注册后，所有未知RPC请求都降调用它，它将传入2个参数，分别是RPC调用名称和参数

### `setVersion($ver)` 

设置服务器版本，在 Console 中的服务管理里可以看到，默认为 1.0

### `setLogger(callable $logger)`
 
设置一个log处理方法，方法接受3个参数，分别是：

* `$type` - 类型，包括：log, info, debug, warn
* `$msg`  - Log内容，字符串或Exception对象
* `$data` - 数组数据，可能是 null

### `addFilter() / removeFilter()` 过滤器

可以方便开发调试

see [https://github.com/hprose/hprose-php/wiki/11-Hprose-过滤器](https://github.com/hprose/hprose-php/wiki/11-Hprose-过滤器)

### Sentry支持

首先需要安装 sentry : `composer require sentry/sdk`, 然后 `XDApp\ServiceReg\Service::sentryInit('https://<key>@<organization>.ingest.sentry.io/<project>');` 即可

更多参数见 [https://docs.sentry.io/error-reporting/configuration/?platform=php](https://docs.sentry.io/error-reporting/configuration/?platform=php)