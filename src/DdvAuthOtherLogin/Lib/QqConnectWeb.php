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
use DdvPhp\QQ\Connect;
use DdvPhp\DdvException;

class QqConnectWeb
{
    public static $SCOPE = 'get_user_info';
    public static function authLogin($params, $config, $userInfoCallback = null, $baseInfoCallback = null){
        if (empty($params['code']) ){
            // 如果这些数据有一个是空的，就代表需要进入授权url生成模式，并且考虑使用静默授权
            return self::authLoginAsUrl($params, $config);
        }else{
            return self::authLoginAsUserInfo($params, $config, $userInfoCallback);
        }
    }
    protected static function authLoginAsUrl($params, $config){
        $authUri = $config['authUri'];
        $redirectUri = '';
        $scope = self::$SCOPE;
        $redirectQuery = array(
            'type'=>$params['type'],
            'getscope' => empty($params['getscope'])?(empty($params['scope'])?self::$SCOPE:$params['scope']):$params['getscope']
        );
        $redirectQuery['redirect_uri'] = empty($params['redirect_uri'])?'':$params['redirect_uri'];

        // 非静默模式
        $scope = empty($params['getscope'])?(empty($params['scope'])?self::$SCOPE:$params['scope']):$params['getscope'];
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
        $qc = new Connect($config);

        $oauth_url = $qc->qq_login($scope, $redirectUri, 'QqConnectWeb');
        // 对外跳转url
        return array(
            'redirectServer' => false,
            'url'=>$oauth_url
        );
    }
    protected static function authLoginAsUserInfo($params, $config, $userInfoCallback = null, $tokenArray = null, $resData = null){
        // 调整地址
        $redirectUri = empty($params['redirect_uri'])?'':$params['redirect_uri'];
        if (empty($tokenArray) || empty($resData)){
            $openid = null;
            $unionid = null;
            $qc = null;
            $tokenStr = null;
            try{
                $qc = new Connect($config);
                $tokenStr = $qc->qq_callback($config['authUri']);
                $res = $qc->get_openid($tokenStr, false);
                $openid = empty($res->openid)?null:$res->openid;
                $unionid = empty($res->unionid)?null:$res->unionid;
            }catch (DdvException $e){
                $tokenStr = null;
            }
            if (empty($qc)||empty($tokenStr)||empty($openid)) {
                $res = self::authLoginAsUrl($params, $config, false);
                $res['redirectServer'] = true;
                return $res;
            }

            $scope = empty($params['getscope'])?(empty($params['scope'])?self::$SCOPE:$params['scope']):$params['getscope'];
            $errorScope = empty($params['error_scope'])?'':$params['error_scope'];
            $scope = empty($scope)?array():explode(',', $scope);
            $errorScope = empty($errorScope)?array():explode(',', $errorScope);
            $resData = array(
                'openid'=>$openid,
                'unionid'=>$unionid,
                'accessToken'=>$tokenStr,
                'scope'=>&$scope,
                'errorScope'=>&$errorScope
            );

            foreach ($scope as $scopet){
                try{
                    $resData['scope_'.$scopet] = $qc->$scopet();;
                    $scopet2 = preg_replace_callback(
                        '(\_\w)',
                        function ($matches) {
                            return strtoupper(substr($matches[0], 1));
                        },
                        (string)$scopet
                    );
                    $resData['scope'.ucfirst($scopet2)] = &$resData['scope_'.$scopet];
                }catch (Exception $e){
                    $errorScope[] = [
                        'scope' => $scopet,
                        'exception'=>$e
                    ];
                }
            }
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
}
