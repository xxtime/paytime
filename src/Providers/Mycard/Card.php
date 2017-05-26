<?php


namespace Xxtime\PayTime\Providers\Mycard;


use Xxtime\PayTime\DefaultException;

class Card
{

    private $sandbox_endpoint_auth = 'https://test.b2b.mycard520.com.tw/MyCardIngameService/Auth';

    private $sandbox_endpoint_redirect = 'https://test.mycard520.com.tw/MyCardIngame/';

    private $sandbox_endpoint_card = 'https://test.b2b.mycard520.com.tw/MyCardIngameService/Confirm';

    private $sandbox_endpoint_query = 'https://test.b2b.mycard520.com.tw/MyCardIngameService/CheckTradeStatus';

    private $endpoint_auth = 'https://b2b.mycard520.com.tw/MyCardIngameService/Auth';

    private $endpoint_redirect = 'https://redeem.mycard520.com/';

    private $endpoint_card = 'https://b2b.mycard520.com.tw/MyCardIngameService/Confirm';

    private $endpoint_query = 'https://b2b.mycard520.com.tw/MyCardIngameService/CheckTradeStatus';

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

        $url = $this->endpoint_auth . '?' . http_build_query($parameter);
        $response = $this->curl_request($url);
        $result = json_decode($response, true);

        if ($result['ReturnMsgNo'] != 1) {
            throw new DefaultException($result['ReturnMsg']);
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
     * @throws DefaultException
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
     * 文档 DOC 3.2
     * 接口地址: https://b2b.mycard520.com.tw/MyCardIngameService/Confirm
     * 传输参数: facId, AuthCode, facMemId, cardId, cardPwd, hash
     * 返回参数: CardKind, CardPoint, SaveSeq, facTradeSeq, oProjNo, ReturnMsgNo, ReturnMsg
     * 返回格式: JSON
     * 返回示例: {"CardKind":0,"CardPoint":0,"SaveSeq":null,"facTradeSeq":null,"oProjNo":null,"ExtensionDat a":null,"ReturnMsg":"參數有誤","ReturnMsgNo":-901}
     * @param array $card [transaction,user_id,auth,card_no,card_pwd]
     * @return array
     * @throws DefaultException
     */
    public function card($card = [])
    {
        //dd($this->config,$card);
        $err_no = [
            '-901' => '參數有誤',
            '-902' => '廠商 ID 有誤',
            '-903' => 'Hash 有誤',
            '-904' => '卡號密碼錯誤', // [已儲值] [已凍結]
        ];
        $card = $card['0'];

        // clear
        $search = [' ', '-'];
        $replace = ['', ''];
        $card['card_no'] = str_replace($search, $replace, strtoupper($card['card_no']));
        $card['card_pwd'] = str_replace($search, $replace, strtoupper($card['card_pwd']));

        // 确认交易并请款
        $key1 = $this->config['app_key1'];
        $key2 = $this->config['app_key2'];
        $user_id = $card['user_id'];
        $param = array(
            'facId'    => $this->config['app_id'],
            'AuthCode' => $card['auth'],
            'facMemId' => $user_id,
            'cardId'   => $card['card_no'],
            'cardPwd'  => $card['card_pwd'],
            'hash'     => hash('sha256',
                "{$key1}{$this->config['app_id']}{$card['auth']}{$user_id}{$card['card_no']}{$card['card_pwd']}{$key2}")
        );
        if ($this->config['sandbox'] == 1) {
            $this->endpoint_card = $this->sandbox_endpoint_card;
        }
        $url = $this->endpoint_card . '?' . http_build_query($param);
        $output = $this->curl_request($url);

        /**
         * TODO:: 请款超时
         * 问题多发生在此处(此时多半请求成功,只是响应超时)
         * 通常请求失败后用户重新提交然后报ReturnMsgNo:-904错误(即已使用错误)
         */
        if ($output === false) {
            // 网关订单查询
            try {
                $query_info = $this->query(['transactionId' => $card['transaction']]);
                return $query_info;
            } catch (DefaultException $e) {
                return $e;
            }
            throw new DefaultException("curl request failed");
        }


        /**
         * TODO :: 由于此处失败不会返回可利用信息, 如:facTradeSeq等
         * 无法根据订单ID进一步验证(需上面出错时卡号与订单关联), 所以即使上面使用超时多次请求也无作用.
         * 已使用卡返回信息: ReturnMsgNo:-904,facTradeSeq:null
         * {"CardKind":0,"CardPoint":0,"SaveSeq":null,"facTradeSeq":null,"oProjNo":null,"ExtensionData":null,"ReturnMsg":"卡號或密碼錯誤,請確認後重新輸入!","ReturnMsgNo":-904}
         * {"CardKind":0,"CardPoint":0,"SaveSeq":null,"facTradeSeq":null,"oProjNo":null,"ExtensionData":null,"ReturnMsg":"您好,此張儲值卡訊息[已儲值],請聯絡MyCard客服人員(02)2651-0754或利用線上客服查詢狀況造成您的不便,請多多見諒","ReturnMsgNo":-904}
         * {"CardKind":0,"CardPoint":0,"SaveSeq":null,"facTradeSeq":null,"oProjNo":null,"ExtensionData":null,"ReturnMsg":"您好,此張儲值卡訊息[已凍結],請聯絡MyCard客服人員(02)2651-0754或利用線上客服查詢狀況造成您的不便,請多多見諒","ReturnMsgNo":-904}
         */
        $response = json_decode($output, true);
        if ($response['ReturnMsgNo'] != 1) {
            if ($response['ReturnMsgNo'] == '-904') {
                throw new DefaultException($response['ReturnMsg']);
            }
            return [
                'isSuccessful' => false,
                'message'      => 'failed',
            ];
        }


        // MyCard货币类型只能TWD
        return [
            'isSuccessful'         => true,
            'message'              => 'success',
            'transactionId'        => $card['transaction'],
            'transactionReference' => $response['SaveSeq'],
            'amount'               => $response['CardPoint'],
            'currency'             => 'TWD',
            'raw'                  => $response
        ];
    }


    /**
     * 查询交易状态 DOC 3.5
     * 接口地址: https://b2b.mycard520.com.tw/MyCardIngameService/CheckTradeStatus
     * 传输参数: facId, facTradeSeq, hash
     * 返回参数: MyCardId, TradeStatus, CardKind, CardPoint, Save_Seq, oProjNo, ReturnMsgNo, ReturnMsg
     * 返回格式: JSON
     * 返回示例: {"MyCardId":null,"TradeStatus":0,"ExtensionData":null,"ReturnMsg":"參數有誤 ","ReturnMsgNo":-901}
     * @param array $param
     * @return bool
     * @throws DefaultException
     */
    public function query($param = [])
    {
        if (empty($param['transactionId'])) {
            return false;
        }
        $data = [
            'facId'       => $this->config['app_id'],
            'facTradeSeq' => $param['transactionId'],
            'hash'        => hash('sha256',
                "{$this->config['app_key1']}{$this->config['app_id']}{$param['transactionId']}{$this->config['app_key2']}")
        ];
        if ($this->config['sandbox'] == 1) {
            $this->endpoint_query = $this->sandbox_endpoint_query;
        }
        $url = $this->endpoint_query . '?' . http_build_query($data);
        $output = $this->curl_request($url);
        if ($output === false) {
            throw new DefaultException('curl request failed');
        }

        $response = json_decode($output, true);
        if ($response['ReturnMsgNo'] != 1) {
            throw new DefaultException($response['ReturnMsg']);
        }


        if ($response['TradeStatus'] != 3) {
            return [
                'isSuccessful' => false,
                'message'      => $response['ReturnMsg'],
            ];
        }

        return [
            'isSuccessful'         => true,
            'message'              => 'success',
            'transactionId'        => $param['transactionId'],
            'transactionReference' => $response['Save_Seq'],
            'amount'               => $response['CardPoint'],
            'currency'             => 'TWD',
            'card_no'              => $response['MyCardId'],
            'raw'                  => $response
        ];
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


    /**
     *  通知回调 文档 3.4
     *  接口地址:
     *  传入参数: facId, facMemId, facTradeSeq, tradeSeq, CardId, oProjNo, CardKind, CardPoint, ReturnMsgNo, ErrorMsgNo, ErrorMsg, hash
     * @throws DefaultException
     */
    public function notify()
    {
        $notice['facId'] = isset($_REQUEST['facId']) ? $_REQUEST['facId'] : '';
        $notice['facMemId'] = isset($_REQUEST['facMemId']) ? $_REQUEST['facMemId'] : '';
        $notice['facTradeSeq'] = isset($_REQUEST['facTradeSeq']) ? $_REQUEST['facTradeSeq'] : '';
        $notice['tradeSeq'] = isset($_REQUEST['tradeSeq']) ? $_REQUEST['tradeSeq'] : '';
        $notice['CardId'] = isset($_REQUEST['CardId']) ? $_REQUEST['CardId'] : '';
        $notice['oProjNo'] = isset($_REQUEST['oProjNo']) ? $_REQUEST['oProjNo'] : '';
        $notice['CardKind'] = isset($_REQUEST['CardKind']) ? $_REQUEST['CardKind'] : '';
        $notice['CardPoint'] = isset($_REQUEST['CardPoint']) ? $_REQUEST['CardPoint'] : '';
        $notice['ReturnMsgNo'] = isset($_REQUEST['ReturnMsgNo']) ? $_REQUEST['ReturnMsgNo'] : '';
        $notice['ErrorMsgNo'] = isset($_REQUEST['ErrorMsgNo']) ? $_REQUEST['ErrorMsgNo'] : '';
        $notice['ErrorMsg'] = isset($_REQUEST['ErrorMsg']) ? $_REQUEST['ErrorMsg'] : '';
        $hash = isset($_REQUEST['hash']) ? $_REQUEST['hash'] : '';

        // 签名验证
        $str = '';
        $str .= $this->config['app_key1'];
        $str .= $notice['facId'];
        $str .= $notice['facMemId'];
        $str .= $notice['facTradeSeq'];
        $str .= $notice['tradeSeq'];
        $str .= $notice['CardId'];
        $str .= $notice['oProjNo'];
        $str .= $notice['CardKind'];
        $str .= $notice['CardPoint'];
        $str .= $notice['ReturnMsgNo'];
        $str .= $notice['ErrorMsgNo'];
        $str .= $notice['ErrorMsg'];
        $str .= $this->config['app_key2'];

        $transactionId = $notice['facTradeSeq'];

        if (hash('sha256', $str) != $hash) {
            throw new DefaultException('sign error');
        }

        $result = [
            'isSuccessful'         => true,
            'message'              => 'success',
            'transactionId'        => $transactionId,
            'transactionReference' => $notice['tradeSeq'],
            'amount'               => $notice['CardPoint'],
            'currency'             => 'TWD',
            'raw'                  => $notice,
        ];
        return $result;
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

}