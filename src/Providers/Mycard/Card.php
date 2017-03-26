<?php


namespace Xxtime\PayTime\Providers\Mycard;


class Card
{

    private $sandbox_endpoint_auth = 'https://test.b2b.mycard520.com.tw/MyCardIngameService/Auth';

    private $sandbox_endpoint_redirect = 'https://test.mycard520.com.tw/MyCardIngame/';

    private $sandbox_endpoint_card = 'https://test.b2b.mycard520.com.tw/MyCardIngameService/Confirm';

    private $endpoint_auth = 'https://b2b.mycard520.com.tw/MyCardIngameService/Auth';

    private $endpoint_redirect = 'https://redeem.mycard520.com/';

    private $endpoint_card = 'https://b2b.mycard520.com.tw/MyCardIngameService/Confirm';

    private $auth_code;     // 授权码

    private $config;        // 配置参数

    private $parameter;     // 产品参数


    public function setOption($option = [])
    {
        if ($option['sandbox'] == 1) {
            $this->endpoint_auth = $this->sandbox_endpoint_auth;
        }
        $this->config = $option;
    }


    public function purchase($option = [])
    {
        $this->parameter = $option;
    }


    /**
     * 获取交易授权码 DOC 3.1
     * 接口地址: https://b2b.mycard520.com.tw/MyCardIngameService/Auth
     * 参数: facId, facTradeSeq, hash
     * 返回参数: AuthCode, TradeType, ReturnMsgNo, ReturnMsg
     * 返回格式: JSON
     * EXP: {"AuthCode":null,"TradeType":0,"ExtensionData":null,"ReturnMsg":"參數有誤 ","ReturnMsgNo":-901}
     */
    private function getAuthCode()
    {
        $parameter = [
            'facId'       => $this->config['app_id'],
            'facTradeSeq' => $this->parameter['transactionId'],
            'hash'        => hash('sha256',
                "{$this->config['app_key1']}{$this->config['app_id']}{$this->parameter['transactionId']}{$this->config['app_key2']}"),
        ];

        $this->endpoint_auth .= '?' . http_build_query($parameter);
        $response = file_get_contents($this->endpoint_auth);
        $result = json_decode($response, true);

        if ($result['ReturnMsgNo'] != 1) {
            throw new \Exception($result['ReturnMsg']);
        }
        $this->auth_code = $result['AuthCode'];

        return $result;
    }


    /**
     * 发起请求
     * @return array
     */
    public function send()
    {
        $result = $this->getAuthCode();

        // 跳转到CDKEY录入界面 DOC 3.2
        if ($result['TradeType'] == 1) {
            return [
                'auth_code' => $this->auth_code,
            ];
        }

        // 跳转到MyCard DOC 3.3
        if ($result['TradeType'] == 2) {
            $this->redirect();
        }
    }


    /**
     * 文档 DOC 3.3
     * 接口地址: https://redeem.mycard520.com
     * 传输参数: AuthCode, facId, facMemId, hash
     * @throws \Exception
     */
    private function redirect()
    {
        if ($this->config['sandbox']) {
            $this->endpoint_redirect = $this->sandbox_endpoint_redirect;
        }
        $key1 = $this->config['app_key1'];
        $key2 = $this->config['app_key2'];
        $param = array(
            'AuthCode' => $this->auth_code,
            'facId'    => $this->config['app_id'],
            'facMemId' => $this->parameter['userId'],
            'hash'     => hash('sha256',
                "{$key1}{$this->auth_code}{$this->config['app_id']}{$this->parameter['userId']}{$key2}")
        );
        $this->endpoint_redirect .= '?' . http_build_query($param);
        header('Location:' . $this->endpoint_redirect);
        exit();
    }


    /**
     * 查询交易状态 DOC 3.5
     * 接口地址: https://b2b.mycard520.com.tw/MyCardIngameService/CheckTradeStatus
     * 传输参数: facId, facTradeSeq, hash
     * 返回参数: MyCardId, TradeStatus, CardKind, CardPoint, Save_Seq, oProjNo, ReturnMsgNo, ReturnMsg
     * 返回格式: JSON
     */
    public function query_status()
    {
    }


    /**
     * 查詢交易或取消查詢 DOC 3.6
     * 接口地址: http://bargain.mycard520.com.tw/MyCardIngameService/MyCardTradeQuerySVreport?facId=facId&facTradeSeq=facTradeSeq&StartDate=StartDate&EndDate=EndDate&Status=Status&hash=hash
     * 传输参数: facId, facTradeSeq, StartDate, EndDate, Status, hash
     * 返回参数: ReturnMsgNo, ReturnMsg, ReturnData[MyCard_ID,TradeSeq,FactoryTradeCode,GameNo,cust_id,State,Card_Kind,CardPoint,oProjNo,tradeok_date,Cancel_Date,Cancel_Status]
     * 返回格式: XML
     */
    public function query_transaction()
    {
    }

}