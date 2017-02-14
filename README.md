## PayTime移动支付SDK
PayTime移动支付SDK



## 功能
* 规范优雅命名
* 符合PSR标准
* 支持多种网关支付
* 统一网关程序
* 订单ID自动生成(日期+自增序列+随机数), 需要Redis支持

## 所需环境
PHP >= 5.5  
composer  

## 安装
```shell
composer require "xxtime/paytime:dev-master"
```


## 使用方法

```php
<?php

use Xxtime\PayTime\Core\PayTime;

$payTime = new PayTime('Alipay_Wap');

$payTime->setOptions(
    array(
        'appId'      => 123456,
        'privateKey' => '/path/to/privateKey.pem',
        'publicKey'  => '/path/to/publicKey.pem',
        'returnUrl'  => 'http://host/returnUrl',
        'notifyUrl'  => 'http://host/notifyUrl',
    );
);

$payTime->purchase([
    'transactionId' => 2016121417340937383,
    'amount'        => 0.05,
    'currency'      => 'CNY',
    'productId'     => 'xxtime.com.product.1',
    'productDesc'   => '测试产品'
]);

$payTime->send();
```


```php
<?php

use Xxtime\PayTime\Core\PayTime;

// 订单验证
$payTime = new PayTime('Alipay');
$response = $payTime->notify();
if (!$response->isSuccessful()) {
    exit('失败');
}
echo '成功';
```
response返回方法：

参数名 | 返回类型 | 描述
--- | --- | ---
isSuccessful  | bool  | 储值结果
transactionId  | varchar(32)  | 订单ID
transactionReference  | varchar(32)  | 充值网关订单ID
amount  | decimal(10,2) | 金额
currency  | varchar(32) | 币种

## 关于项目
主页: [https://github.com/xxtime/PayTime](https://github.com/xxtime/PayTime)  
作者: [https://www.xxtime.com](https://www.xxtime.com)  
