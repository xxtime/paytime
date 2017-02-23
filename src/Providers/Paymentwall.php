<?php

/**
 * @link https://www.paymentwall.com/en/documentation/Digital-Goods-API/710
 */

namespace Xxtime\PayTime\Providers;


class Paymentwall
{

    protected $endpoint = 'https://api.paymentwall.com/api/subscription';


    private $_trade;


    private $_config;


    /**
     * @param array $option
     */
    public function setOption($option = [])
    {
        $this->_config = $option;

        $this->_trade['key'] = $option['app_id'];
        $this->_trade['widget'] = 'm2';             // 样式 m2,m2_1,p1,p1_1,p2,fp; fp要求录入信息
        $this->_trade['ag_type'] = 'fixed';         // fixed / subscription, 固定产品或活动产品

        /*
         * 其他设置
        $this->_trade['success_url'] = '';          // 成功后跳转
        $this->_trade['failure_url'] = '';          // 失败后跳转
        */

        // 设置但未生效 ？
        if (!empty($option['notify_url'])) {
            $this->_trade['pingback_url'] = $option['notify_url'];  // pingback_url ,设置此项会覆盖paymentwall后台设置
        }
    }


    /**
     * @param array $option
     */
    public function purchase($option = [])
    {
        $this->_trade['uid'] = $option['transactionId'];
        $this->_trade['amount'] = $option['amount'];            // 金额
        $this->_trade['currencyCode'] = $option['currency'];    // 货币类型
        $this->_trade['ag_name'] = $option['productDesc'];      // 产品名称
        $this->_trade['ag_external_id'] = $option['productId']; // 产品ID, pingback回传

        $this->_trade['ts'] = time();                           // 时间戳
        $this->_trade['sign_version'] = 2;                      // 签名版本
        $this->_trade['sign'] = $this->createSign($this->_trade, $this->_config['app_key'], '=', '');   // 签名

        /*
        $this->_trade['ps'] = 'cc';                 // 指定支付渠道时使用
        $this->_trade['ag_period_length'] = 3;      // numeric, 仅ag_type=subscription需要
        $this->_trade['ag_period_type'] = 'week';   // day / week / month / year, 仅ag_type=subscription需要
        $this->_trade['ag_promo'] = '';             // 自定义说明e.g. "Save 20%" 显示在paymentwall产品选择页面(需要二次选择产品)
        $this->_trade['country_code'] = 'CN';       // 覆盖默认GEO定位,根据此设置显示支付渠道,忽略则根据geo定位
        $this->_trade['email'] = '';                // 用户邮箱,paymentwall 会向此邮箱发账单邮件
        */
    }


    public function send()
    {
        $url = $this->endpoint . '?' . http_build_query($this->_trade);
        header("Location:" . $url);
        exit();
    }


    /**
     * 通知 PingBack
     * @demo /100/notify/paymentwall
     * @demo /notify/paymentwall?uid=201702230426692850002665&goodsid=com.xt.2&slength=&speriod=&type=0&ref=t1487824037&is_test=1&sign_version=2&sig=3eaf5f2ddf5946e4175bda982ac13b41
     *
     * 如未指定产品，则必须配置paymentwall后台的自定义参数 Settings -> Custom Pingback parameters
     * 例:
     * amount   PRODUCT_PRICE
     * currency PRODUCT_CURRENCY_CODE
     *
     */
    public function notify()
    {
        $req = $_REQUEST;
        $sign = $req['sig'];
        unset($req['_url']);
        unset($req['sig']);
        $verify_sign = $this->createSign($req, $this->_config['app_key'], '=', '');


        // 验签不通过
        if ($sign != $verify_sign) {
            return [
                'transactionId'        => $req['uid'],
                'transactionReference' => $req['ref'],
                'isSuccessful'         => false,
                'message'              => 'failed',
            ];
        }


        // 成功返回
        $result = [
            'transactionId'        => $req['uid'],
            'transactionReference' => $req['ref'],
            'isSuccessful'         => true,
            'message'              => 'success',
        ];
        if (isset($req['is_test'])) {
            $result['sandbox'] = true;
        }

        return $result;
    }


    /**
     * 输出
     */
    public function success()
    {
        exit('ok');
    }


    private function createSign($data = array(), $sign_key = '', $as = '=', $di = '&')
    {
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $string .= "$key{$as}$value{$di}";
        }
        return md5(rtrim($string, $di) . $sign_key);
    }

}