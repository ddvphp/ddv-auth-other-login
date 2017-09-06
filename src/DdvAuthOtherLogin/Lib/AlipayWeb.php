<?php
/**
 * Created by PhpStorm.
 * User: hua
 * Date: 2017/9/4
 * Time: 下午5:33
 */

namespace DdvPhp\DdvAuthOtherLogin\Lib;
use \Closure;
use DdvPhp\Alipay\AopSdk;
use DdvPhp\DdvAuthOtherLogin\Exception;

class AlipayWeb
{
    public static $SCOPE_AUTH_BASE = 'auth_base';
    public static $OPEN_AUTH_URL = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?';
    public static function authLogin($params, $config, $userInfoCallback = null, $baseInfoCallback = null){
        // 是否需要尝试静默授权
        $isAutoTryBaseLogin = empty($params['nbauth']) && (boolean)$config['isAutoTryBaseLogin'] && ($baseInfoCallback instanceof Closure);
        if (empty($params['app_id']) || empty($params['auth_code']) || empty($params['scope'])){
            // 如果这些数据有一个是空的，就代表需要进入授权url生成模式，并且考虑使用静默授权
            return self::authLoginAsUrl($params, $config, $isAutoTryBaseLogin);
        }else{
            return $isAutoTryBaseLogin ? self::authLoginAsBase($params, $config, $baseInfoCallback) : self::authLoginAsUserInfo($params, $config, $userInfoCallback);
        }
    }
    protected static function authLoginAsUrl($params, $config, $isAutoTryBaseLogin = false){
        $authUri = $config['authUri'];
        $redirectUri = '';
        $query = array(
            'app_id'=>$config['appId']
        );
        $redirectQuery = array(
            'type'=>$params['type'],
            'nbauth'=>empty($params['nbauth'])?'':$params['nbauth'],
            'getscope' => empty($params['getscope'])?(empty($params['scope'])?self::$SCOPE_AUTH_BASE:$params['scope']):$params['getscope']
        );
        $redirectQuery['touri'] = empty($params['touri'])?'':$params['touri'];
        if (empty($params['touri'])){
            $redirectQuery['touri'] = empty($params['redirect_uri'])?'':str_replace('/','_',base64_encode($params['redirect_uri']));
        }

        // 是否静默模式
        if ($isAutoTryBaseLogin){
            // 静默模式尝试
            $query['scope'] = self::$SCOPE_AUTH_BASE;
        }else{
            // 非静默模式
            $query['scope'] = empty($params['getscope'])?(empty($params['scope'])?self::$SCOPE_AUTH_BASE:$params['scope']):$params['getscope'];
        }
        if (strpos($authUri,'?')===false){
            $authUri .= '?';
        }else{
            if (substr($authUri, -1)!=='&'){
                $authUri .= '&';
            }
        }
        $query['redirect_uri'] = $authUri.http_build_query($redirectQuery);
        // 对外跳转url
        return array(
            'redirectServer' => false,
            'url'=>self::$OPEN_AUTH_URL . http_build_query($query)
        );
    }
    protected static function authLoginAsBase($params, $config, Closure $baseInfoCallback){
        try{
            $token = self::requestToken($params['auth_code'], $config);
            $tokenArray = self::tokenStdClassToArray($token);
        }catch (Exception $e){
            $params['nbauth'] = '1';
            $res = self::authLoginAsUrl($params, $config, false);
            $res['redirectServer'] = true;
            return $res;
        }
        $resData = array(
            'alipayUserId'=>$tokenArray['alipay_user_id'],
            'userId'=>$tokenArray['user_id']
        );
        $res = $baseInfoCallback($resData, $token);
        if ($res!==true){
            $params['nbauth'] = '1';
            $res = self::authLoginAsUrl($params, $config, false);
            $res['redirectServer'] = true;
            return $res;
        }
        return array(
            'redirectServer' => false
        );
    }
    protected static function authLoginAsUserInfo($params, $config, $userInfoCallback = null){
        // 调整地址
        $toUri = base64_decode(str_replace('_','/',(empty($params['touri'])?'':$params['touri'])));
        $tokenStr = '';
        try{
            $token = self::requestToken($params['auth_code'], $config);
            $tokenArray = self::tokenStdClassToArray($token);
            if (empty($tokenArray['access_token'])){
                throw new Exception('access token error', 'ACCESS_TOKEN_ERROR');
            }
            $tokenStr = $tokenArray['access_token'];
        }catch (Exception $e){
            $params['nbauth'] = '1';
            $res = self::authLoginAsUrl($params, $config, false);
            $res['redirectServer'] = true;
            return $res;
        }
        $scope = explode(',', (empty($params['scope'])?'':$params['scope']));
        $errorScope = explode(',', (empty($params['error_scope'])?'':$params['error_scope']));
        $resData = array(
            'alipayUserId'=>empty($tokenArray['alipay_user_id'])?(empty($tokenArray['alipayUserId'])?'':$tokenArray['alipayUserId']):$tokenArray['alipay_user_id'],
            'userId'=>empty($tokenArray['user_id'])?(empty($tokenArray['userId'])?'':$tokenArray['userId']):$tokenArray['user_id'],
            'scope'=>&$scope,
            'errorScope'=>&$errorScope,
            'res'=>array()
        );

        foreach ($scope as $scopet){
            if ($scopet==='auth_base'){
                continue;
            }
            $scopet2 = preg_replace_callback(
                '(\_\w)',
                function ($matches) {
                    return strtoupper(substr($matches[0], 1));
                },
                (string)$scopet
            );
            $methodName = 'request'.ucfirst($scopet2);
            if (method_exists(self::class, $methodName)){
                try{
                    $resData['res'][$scopet] = call_user_func_array(array(self::class, $methodName),array($tokenStr, $config));
                    $resData['res'][$scopet2] = &$resData['res'][$scopet];
                }catch (Exception $e){
                    $errorScope[] = $scopet;
                }
            }else{
                throw new Exception($methodName.'不存在', 'REQUEST_METHOD_NOT_FIND');
            }
        }
        $res = array(
            'redirectServer' => false,
            'isEnd' => true
        );
        if ($userInfoCallback instanceof Closure){
            $userInfoCallback($resData, $token);
        }else{
            $res['res'] = $resData;
        }
        return $res;
    }

    public static function requestAuthUserinfo($token, $config) {
        $AlipayUserUserinfoShareRequest = new \AlipayUserUserinfoShareRequest();

        $result = AopSdk::aopclientRequestExecute(AopSdk::getAopClient($config), $AlipayUserUserinfoShareRequest, $token );

        if (isset ( $result->alipay_user_userinfo_share_response )) {
            $res = array();
            foreach ($result->alipay_user_userinfo_share_response as $key => $value){
                $res[$key] = $value;
            }
            return $res;
        } elseif (isset ( $token->error_response )) {
            throw new Exception( $token->error_response->sub_msg, $token->error_response->sub_code, $token->error_response->code);
        }
        throw new Exception('获取授权信息失败', 'AUTH_USERINFO_FAIL');
    }
    public static function requestToken($authCode, $config) {
        AopSdk::init();
        $AlipaySystemOauthTokenRequest = new \AlipaySystemOauthTokenRequest ();
        $AlipaySystemOauthTokenRequest->setCode ( $authCode );
        $AlipaySystemOauthTokenRequest->setGrantType ( "authorization_code" );


        $result = AopSdk::aopclientRequestExecute(AopSdk::getAopClient($config), $AlipaySystemOauthTokenRequest );
        return $result;
    }
    protected static function tokenStdClassToArray($stdClass){
        if (is_object($stdClass)&&property_exists($stdClass, 'alipay_system_oauth_token_response')){
            $r = array();
            foreach ($stdClass->alipay_system_oauth_token_response as $key => $value){
                $r[$key] = $value;
            }
            return $r;
        }
        throw new Exception('不是一个有效的token stdClass', 'NOT_TOKEN_STDCLASS');
    }
}