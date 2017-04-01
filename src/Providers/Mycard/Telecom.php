<?php


/**
 * MyCard电信类支付
 * TODO :: 为避免不必要麻烦, CP会员ID均使用transactionId代替
 * TODO :: DOC 4.5 辅储没有auth_code 需要单独处理
 */
namespace Xxtime\PayTime\Providers\Mycard;


use Exception;

class Telecom
{

    private $endpoint = 'https://b2b.mycard520.com.tw/MyCardBillingRESTSrv/MyCardBillingRESTSrv.svc';

    private $endpoint_redirect = 'https://www.mycard520.com.tw/MyCardBilling/';

    private $sandbox_endpoint = 'https://test.b2b.mycard520.com.tw/MyCardBillingRESTSrv/MyCardBillingRESTSrv.svc';

    private $sandbox_endpoint_redirect = 'https://test.mycard520.com.tw/MyCardBilling/';

    private $options;       // 配置选项

    private $parameters;    // 产品参数

    private $sandbox = false;

    private $redirect;

    private $auth_code;

    private $transactionReference;


    public function setOption($options = [])
    {
        $this->options = $options;
    }


    public function purchase($parameters = [])
    {
        $this->parameters = $parameters;
    }


    /**
     * 请求储值
     * @return array
     * @throws Exception
     */
    public function send()
    {
        if (!$this->auth_code) {
            $this->getAuthCode();
        }

        return [
            'redirect'             => $this->redirect,
            'transactionReference' => $this->transactionReference,
            'auth_code'            => $this->auth_code,
        ];
    }


    /**
     * 跳转
     * @throws Exception
     */
    public function redirect()
    {
        if (!$this->auth_code) {
            $this->getAuthCode();
        }
        header('Location:' . $this->redirect);
        exit;
    }


    /**
     * 回调确认 DOC 4.5 未完成
     * @return array
     * @throws Exception
     */
    public function notify()
    {
        $code = isset($_REQUEST['ReturnMsgNo']) ? $_REQUEST['ReturnMsgNo'] : '';
        $msg = isset($_REQUEST['ReturnMsg']) ? $_REQUEST['ReturnMsg'] : '';
        $this->auth_code = isset($_REQUEST['AuthCode']) ? $_REQUEST['AuthCode'] : '';
        $transactionId = isset($_REQUEST['TradeSeq']) ? $_REQUEST['TradeSeq'] : '';


        /**
         * 辅储 DOC 4.5
         * TODO :: 辅储没有auth_code
         */
        $auxiliary = isset($_REQUEST['data']) ? $_REQUEST['data'] : '';
        if ($auxiliary) {
            $auxiliary = $this->xml2array($auxiliary);
            foreach ($auxiliary['Records']['Record'] as $value) {
                $transactionId = $value['TradeSeq'];
                $this->auth_code = ''; // TODO :: 需要单独获取auth_code
            }
        }


        try {
            $this->verifyAndConfirm($transactionId);
        } catch (\Exception $e) {
            throw $e;
        }

        $result = [
            'isSuccessful'         => true,
            'message'              => 'success',
            'transactionId'        => $transactionId,
            'transactionReference' => '',
            'amount'               => '',
            'currency'             => '',
            'raw'                  => '',
        ];

        return $result;

    }


    /**
     * 验证并请款 DOC 3.3 & DOC 3.4
     * @param string $transactionId
     * @return bool
     * @throws Exception
     */
    private function verifyAndConfirm($transactionId = '')
    {
        if ($this->sandbox) {
            $this->endpoint = $this->sandbox_endpoint;
        }

        // 验证 DOC 3.3
        $url = $this->endpoint . '/TradeQuery?AuthCode=' . $this->auth_code;
        $response = $this->xml2array($this->curl_request($url));
        $output = explode('|', $response['0']);
        if ($output['0'] != 1) {
            throw new Exception($output['1']);
        }

        // 确认交易 DOC 3.4
        // TODO :: 为避免不必要麻烦 所有 CPCustId 均使用 transactionId代替
        $url = $this->endpoint . '/PaymentConfirm?CPCustId=' . $transactionId . '&AuthCode=' . $this->auth_code;
        $response = $this->xml2array($this->curl_request($url));
        $output = explode('|', $response['0']);
        if ($output['0'] != 1) {
            throw new Exception($output['1']);
        }

        return true;
    }


    /**
     * 获取交易授权码 DOC 3.1
     * 传输参数: ServiceId, TradeSeq, PaymentAmount
     * 回传格式: Transaction Code / Transaction Message /MyCard Trade Sequence Number / Transaction auth code
     * @throws \Exception
     */
    private function getAuthCode()
    {
        $amount = intval($this->parameters['amount']);
        if (!$this->sandbox) {
            $url = $this->endpoint . "/Auth/{$this->parameters['custom']}/{$this->parameters['transactionId']}/{$amount}";
            $url_redirect = $this->endpoint_redirect;
        } else {
            $url = $this->sandbox_endpoint . "/Auth/{$this->options['app_key']}/{$this->parameters['transactionId']}/{$amount}";
            $url_redirect = $this->sandbox_endpoint_redirect;
        }
        $response = $this->xml2array($this->curl_request($url));
        $output = explode('|', $response['0']);
        if ($output['0'] != 1) {
            throw new \Exception($output['1']);
        }

        $this->transactionReference = $output['2'];
        $this->auth_code = $output['3'];
        $this->redirect = $url_redirect . '?AuthCode=' . $this->auth_code;
    }


    /**
     * curl request
     * @param string $url
     * @return mixed
     */
    private function curl_request($url = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }


    private function xml2array($xml = '')
    {
        $data = simplexml_load_string(
            $xml
            , null
            , LIBXML_NOCDATA
        );
        $array = json_decode(json_encode($data), true);
        return $array;
    }

}