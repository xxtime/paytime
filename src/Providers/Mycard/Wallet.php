<?php


/**
 * 可指定任意金额交易
 * TODO 未实现:: 3.5 查询交易
 */

namespace Xxtime\PayTime\Providers\Mycard;


class Wallet
{

    private $sandbox_endpoint_auth = "https://test.b2b.mycard520.com.tw/MyCardPointPaymentServices/MyCardPpServices.asmx/MyCardMemberServiceAuth";

    private $sandbox_endpoint_login = 'http://test.member.mycard520.com.tw/MemberLoginService/';

    private $sandbox_endpoint_confirm = "https://test.b2b.mycard520.com.tw/MyCardPointPaymentServices/MyCardPpServices.asmx/MemberCostListRender";

    private $endpoint_auth = "https://b2b.mycard520.com.tw/MyCardPointPaymentServices/MyCardPpServices.asmx/MyCardMemberServiceAuth";

    private $endpoint_login = 'https://member.mycard520.com.tw/MemberLoginService/';

    private $endpoint_confirm = 'https://b2b.mycard520.com.tw/MyCardPointPaymentServices/MyCardPpServices.asmx/MemberCostListRender';

    private $auth_code;             // MyCard Auth Code

    private $transactionReference;  // MyCard transaction Reference

    private $redirect;

    private $config;

    private $parameter;


    public function setOption($option = [])
    {
        if ($option['sandbox'] == 1) {
            $this->endpoint_auth = $this->sandbox_endpoint_auth;
        }
        $this->config = $option;
    }


    public function purchase($option = [])
    {
        $this->parameter = [
            'FactoryId'        => $this->config['app_id'],
            'FactoryServiceId' => $this->config['app_key'],
            'FactorySeq'       => $option['transactionId'],
            'PointPayment'     => intval($option['amount']),
            'BonusPayment'     => 0,
            'FactoryReturnUrl' => $this->config['notify_url'],
        ];
    }


    /**
     * 获取交易授权吗
     * @throws \Exception
     */
    private function getAuthCode()
    {
        $err_no = [
            '1'   => '成功',
            '-11' => '錯誤的廠商 ID',
            '-12' => '廠商 ID 與當初申請之 IP 不符',
            '-13' => '此服務代碼目前無法使用',
            '-14' => '此服務代碼已超過使用期限',
            '-15' => '金額不符',
            '-16' => '金額不符',
            '-17' => '商家交易序號重複',
            '-19' => '系統發生問題',
        ];


        // 获取授权码
        $this->endpoint_auth .= '?' . http_build_query($this->parameter);
        $response = file_get_contents($this->endpoint_auth);
        $response = $this->xml2array($response);
        if ($response['ReturnMsgNo'] != 1) {
            $msg = $err_no[$response['ReturnMsgNo']];
            throw new \Exception($msg);
        }


        // 跳转URL
        if ($this->config['sandbox']) {
            $this->endpoint_login = $this->sandbox_endpoint_login;
        }

        $this->redirect = $this->endpoint_login . '?AuthCode=' . $this->auth_code;
        $this->transactionReference = $response['ReturnTradeSeq'];  // 交易ID For MyCard
        $this->auth_code = $response['ReturnAuthCode'];             // 交易授权码
    }


    /**
     * @return array
     * @throws \Exception
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
     * @throws \Exception
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
     * 1. 接受通知
     * 2. 调用交易确认接口
     */
    public function notify()
    {
        $err_no = [
            '1'    => '登入成功',
            '-21'  => '查無此 AuthCode',
            '-22'  => '此 AuthCode 已逾時',
            '-23'  => '查無相關之金流服務',
            '-24'  => '查無相關之廠商 ID',
            '-31'  => 'MyCard 系統發生錯誤',
            '-39'  => 'MyCard 系統發生錯誤',
            '-40'  => 'MyCard 系統發生錯誤',
            '-41'  => 'MyCard 系統發生錯誤',
            '-99'  => 'MyCard 系統發生錯誤',
            '-101' => '使用者登入失敗',
            '-102' => '儲值超出每天上限',
            '-103' => '儲值超出每月上限',
            '-104' => '檢查系統上限出錯',
            '-111' => '此 ip 會黑名單',
            '-987' => '簡易會員',
        ];
        $code = $_REQUEST['ReturnMsgNo'];
        $transactionId = $_REQUEST['FactorySeq'];
        $OTP = $_REQUEST['OTP'];
        $auth_code = $_REQUEST['AuthCode'];
        if ($code != 1) {
            $msg = $err_no[$code];
            throw new \Exception($msg);
        }


        // 确认交易
        $err_no = [
            '1'    => '要求成功',
            '-801' => '取得扣點資料時發生例外',
            '-802' => '取得扣點資料時發生錯誤',
            '-803' => '扣點發生例外',
            '-804' => '扣點發生錯誤',
            '-805' => '內部更新資料發生錯誤',
        ];
        if ($this->config['sandbox']) {
            $this->endpoint_confirm = $this->sandbox_endpoint_confirm;
        }
        $param = ['AuthCode' => $auth_code, 'OneTimePassword' => $OTP];
        $this->endpoint_confirm .= '?' . http_build_query($param);
        $response = file_get_contents($this->endpoint_confirm);
        $response = $this->xml2array($response);

        if ($response['ReturnMsgNo'] != 1) {
            $msg = $err_no[$code];
            throw new \Exception($msg);
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