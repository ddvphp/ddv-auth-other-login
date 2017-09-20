<?php
/**
 * Created by PhpStorm.
 * User: hua
 * Date: 2017/9/4
 * Time: 下午5:33
 */

namespace DdvPhp\DdvAuthOtherLogin\Lib;
use \Closure;
use DdvPhp\DdvAuthOtherLogin\Exception;
use DdvPhp\DdvRestfulApi;
use DdvPhp\Wechat;

class WechatMp
{
    public static $SNAPI_BASE = 'snsapi_base';
    public static $SNAPI_USERINFO = 'snsapi_userinfo';
    public static function authLogin($params, $config, $userInfoCallback = null, $baseInfoCallback = null){
        // 是否需要尝试静默授权
        $isAutoTryBaseLogin = empty($params['nbauth']) && (boolean)$config['isAutoTryBaseLogin'] && ($baseInfoCallback instanceof Closure);
        if (empty($params['code']) ){
            // 如果这些数据有一个是空的，就代表需要进入授权url生成模式，并且考虑使用静默授权
            return self::authLoginAsUrl($params, $config, $isAutoTryBaseLogin);
        }else{
            return $isAutoTryBaseLogin ? self::authLoginAsBase($params, $config, $baseInfoCallback, $userInfoCallback) : self::authLoginAsUserInfo($params, $config, $userInfoCallback);
        }
    }
    protected static function authLoginAsUrl($params, $config, $isAutoTryBaseLogin = false){
        $authUri = $config['authUri'];
        $redirectUri = '';
        $scope = self::$SNAPI_BASE;
        $redirectQuery = array(
            'type'=>$params['type'],
            'nbauth'=>empty($params['nbauth'])?'':$params['nbauth'],
            'getscope' => empty($params['getscope'])?(empty($params['scope'])?self::$SNAPI_USERINFO:$params['scope']):$params['getscope']
        );
        $redirectQuery['redirect_uri'] = empty($params['redirect_uri'])?'':$params['redirect_uri'];

        // 是否静默模式
        if ($isAutoTryBaseLogin){
            // 静默模式尝试
            $scope = self::$SNAPI_BASE;
        }else{
            // 非静默模式
            $scope = empty($params['getscope'])?(empty($params['scope'])?self::$SNAPI_USERINFO:$params['scope']):$params['getscope'];
        }
        if (strpos($authUri,'?')===false){
            $authUri .= '?';
        }else{
            if (substr($authUri, -1)!=='&'){
                $authUri .= '&';
            }
        }
        $redirectUri = $authUri.http_build_query($redirectQuery);
        if (!empty($config['isDdvRestfulApi'])){
            // 取得接口单例
            $apiobj = DdvRestfulApi::getInstance();
            $redirectUri = $apiobj->getSignUrlByUrl($redirectUri, true);
        }
        $weObj = new Wechat($config);
        $oauth_url = $weObj->getOauthRedirect($redirectUri, 'wxbase', $scope);
        // 对外跳转url
        return array(
            'redirectServer' => false,
            'url'=>$oauth_url
        );
    }
    protected static function authLoginAsBase($params, $config, Closure $baseInfoCallback, Closure $userInfoCallback){
        // 调整地址
        $redirectUri = empty($params['redirect_uri'])?'':$params['redirect_uri'];
        try{
            $tokenArray = self::requestToken($params['code'], $config);
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

        $resData = self::requestAuthUserinfo($tokenArray, $config);

        $res = $baseInfoCallback($resData, $tokenArray);


        if ($res!==true){
            if ($resData['isAuthUserInfo']){
                return self::authLoginAsUserInfo($params, $config, $userInfoCallback, $tokenArray, $resData);
            }
            $params['nbauth'] = '1';
            $res = self::authLoginAsUrl($params, $config, false);
            $res['redirectServer'] = true;
            return $res;
        }
        return array(
            'redirectServer' => true,
            'url'=>$redirectUri,
            'isEnd' => true
        );
    }
    protected static function authLoginAsUserInfo($params, $config, $userInfoCallback = null, $tokenArray = null, $resData = null){
        // 调整地址
        $redirectUri = empty($params['redirect_uri'])?'':$params['redirect_uri'];
        if (empty($tokenArray) || empty($resData)){
            try{
                $tokenArray = self::requestToken($params['code'], $config);
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

            $resData = self::requestAuthUserinfo($tokenArray, $config);
        }

        $res = array(
            'redirectServer' => true,
            'url'=>$redirectUri,
            'isEnd' => true
        );
        if ($userInfoCallback instanceof Closure){
            $userInfoCallback($resData, $tokenArray);
        }else{
            $res['res'] = $resData;
        }
        return $res;
    }

    public static function requestAuthUserinfo($tokenArray, $config) {
        $resData = array(
            'openid'=>$tokenArray['openid'],
            'unionid'=>empty($tokenArray['unionid'])? null : $tokenArray['unionid'],
            'refreshToken'=>$tokenArray['refresh_token'],
            'accessToken'=>$tokenArray['access_token'],
            'accessTokenExpiresIn'=>$tokenArray['expires_in']
        );
        $scopeArray = explode(',',(empty($tokenArray['scope'])?'':$tokenArray['scope']));
            $weObj = new Wechat($config);
        $userInfo = $weObj->getUserInfo($resData['openid']);
        if (is_array($userInfo)){
            $resData = array_merge($resData, $userInfo);
        }
        foreach ( explode(' ', 'nickname country province city headimgurl') as $key){
            if (isset($resData['isAuthUserInfo'])&&$resData['isAuthUserInfo']===true){
                break;
            }
            $resData['isAuthUserInfo'] = (!empty($resData[$key]));
        }
        if (in_array(self::$SNAPI_USERINFO, $scopeArray)){
            $userInfo = $weObj->getOauthUserinfo($resData['accessToken'], $resData['openid']);
            if (is_array($userInfo)){
                $resData = array_merge($resData, $userInfo);
                $resData['isAuthUserInfo'] = true;
            }
        }

        return $resData;
    }
    public static function requestToken($authCode, $config) {
        $weObj = new Wechat($config);
        $_GET['code'] = empty($authCode)?$_GET['code']:$authCode;
        $json = $weObj->getOauthAccessToken();

        return $json;
    }
}
