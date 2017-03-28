<?php

/**
 * 网站标准版WPS (Website Payment Standard)
 *
 * DOC 集成
 * @see https://www.paypal-biz.com/development/documentation/PayPal_WPS_Guide_CN_V2.0.pdf
 * @see https://developer.paypal.com/webapps/developer/docs/classic/paypal-payments-standard/integration-guide/Appx_websitestandard_htmlvariables/
 *
 * DOC 回调
 * @see https://www.paypal-biz.com/product/pdf/PayPal_IPN&PDT_Guide_V1.0.pdf
 * @see https://developer.paypal.com/webapps/developer/docs/classic/ipn/integration-guide/IPNandPDTVariables/
 */
namespace Xxtime\PayTime\Providers\Paypal;


class Wps
{

    private $endpoint = 'https://www.paypal.com';

    private $sandbox_endpoint = 'https://www.sandbox.paypal.com';

    private $options;       // 配置选项

    private $parameters;    // 产品参数


    /**
     * 设置选项
     * @param array $options
     */
    public function setOption($options = [])
    {
        $this->options = $options;

        // IPN TEST MODEL
        if (!empty($_REQUEST['test_ipn'])) {
            $this->endpoint = $this->sandbox_endpoint;
        }
    }


    public function purchase($parameters = [])
    {
        $this->parameters = $parameters;
    }


    /**
     * 发送请求
     * @return mixed
     */
    public function send()
    {
        $this->redirect();
    }


    /**
     * 跳转
     */
    public function redirect()
    {
        $email = $this->options['email'];
        $parameter['invoice'] = $this->parameters['transactionId'];     // 若无则留空, 不允许重复订单号
        $parameter['cmd'] = '_xclick';
        $parameter['item_name'] = $this->parameters['productDesc'];     // 商品名称
        $parameter['amount'] = $this->parameters['amount'];
        $parameter['currency_code'] = $this->parameters['currency'];
        $parameter['business'] = $email;
        $parameter['item_number'] = $this->parameters['productId'];     // 产品识别号
        $parameter['quantity'] = 1;
        $parameter['no_note'] = 1;                                      // 不允许买家留言设置1
        $parameter['custom'] = $this->parameters['custom'];

        $parameter['return'] = $this->options['return_url'];            // PDT回调地址
        $parameter['cancel_return'] = $this->options['cancel_url'];     // 失败回调地址
        $parameter['notify_url'] = $this->options['notify_url'];        // IPN回调地址

        // 返回
        $response = $this->build_request_form($parameter, 'post');
        exit($response);
    }


    /**
     * https://mapi.alipay.com/gateway.do?_input_charset=utf-8
     * @param $param_data
     * @param string $method
     * @param string $button_name
     * @return string
     */
    private function build_request_form($param_data, $method = 'post', $button_name = 'submit')
    {
        $url = $this->endpoint . '/cgi-bin/webscr';

        $sHtml = "Loading...<form style='display:none;' id='myPaySubmit' name='myPaySubmit' action='{$url}' method='{$method}'>";
        while (list ($key, $val) = each($param_data)) {
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
        }

        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml . "<input type='submit' value='" . $button_name . "'></form>";

        //提交表单
        $sHtml = $sHtml . "<script>document.forms['myPaySubmit'].submit();</script>";

        return $sHtml;
    }


    /**
     * 通知回调
     * 示例数据: /notify/paypal?paypal&mc_gross=0.98&invoice=262986&protection_eligibility=Eligible&address_status=confirmed&payer_id=M2PSELDUQ66VJ&address_street=Erlenweg 9&payment_date=17:32:51 Mar 27, 2017 PDT&payment_status=Completed&charset=gb2312&address_zip=72474&first_name=Harald&mc_fee=0.34&address_country_code=DE&address_name=Harald Schwenold&notify_version=3.8&custom=6106_115972_8008_51696&payer_status=verified&business=business_ads@gamehetu.com&address_country=Germany&address_city=Winterlingen&quantity=1&verify_sign=Al.sEMmQrthTGy.423ZL7jGr63saAvQwlTQOhnNh8VouJGeSbAsyRHy3&payer_email=Haraldschwenold@gmx.de&txn_id=59413309V07840737&payment_type=instant&last_name=Schwenold&address_state=&receiver_email=business_ads@gamehetu.com&payment_fee=0.34&receiver_id=2LGQG9DDF7XV4&txn_type=web_accept&item_name=6106&mc_currency=USD&item_number=germany.paypal8&residence_country=DE&transaction_subject=&payment_gross=0.98&ipn_track_id=44560f9a1b7fb
     * @return bool
     * @throws \Exception
     */
    public function notify()
    {
        $url = $this->endpoint . '/cgi-bin/webscr';
        $request = array_merge($_GET, $_POST);

        // 检查订单状态 TODO::台币回调payment_status 状态Pending, 美元Completed
        $status = $request['payment_status'];
        if ($status != 'Completed') {
            return false;
        }

        // IPN 方式
        $req = 'cmd=_notify-validate';
        foreach ($request as $key => $value) {
            $value = urlencode(stripslashes($value));
            $req .= "&$key=$value";
        }
        $response = file_get_contents($url, false, stream_context_create(array(
            'http' => array(
                'timeout' => 30,
                'method'  => 'POST',
                'header'  => 'Content-Type:application/x-www-form-urlencoded;',
                'content' => $req
            )
        )));

        // VERIFIED | INVALID
        if ($response == 'INVALID') {
            throw new \Exception('verify failed');
        }


        // 订单信息
        $transactionId = $request['invoice'];
        $transactionReference = $request['txn_id'];
        $amount = $request['mc_gross'];
        $currency = $request['mc_currency'];
        $custom = $request['custom'];
        $product_id = $request['item_number'];
        $receiver_email = $request['receiver_email'];
        $payer_email = $request['payer_email'];


        $result = [
            'isSuccessful'         => true,
            'message'              => 'success',
            'transactionId'        => $transactionId,
            'transactionReference' => $transactionReference,
            'amount'               => $amount,
            'currency'             => $currency,
            'raw'                  => $request,
        ];
        return $result;
    }

}