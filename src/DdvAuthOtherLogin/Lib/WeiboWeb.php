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
use DdvPhp\DdvException;
use DdvPhp\Weibo\SaeTClientV2;
use DdvPhp\Weibo\SaeTOAuthV2;

class WeiboWeb
{
    public static $SNAPI_BASE = 'snsapi_base';
    public static $SNAPI_USERINFO = 'all,email,invitation_write,follow_app_official_microblog';
    public static function authLogin($params, $config, $userInfoCallback = null, $baseInfoCallback = null){
        // 是否需要尝试静默授权
        $isAutoTryBaseLogin = empty($params['nbauth']) && (boolean)$config['isAutoTryBaseLogin'] && ($baseInfoCallback instanceof Closure);
        if (empty($params['code']) ){
            // 如果这些数据有一个是空的，就代表需要进入授权url生成模式，并且考虑使用静默授权
            return self::authLoginAsUrl($params, $config, $isAutoTryBaseLogin);
        }else{
            return $isAutoTryBaseLogin ? self::authLoginAsBase($params, $config, $baseInfoCallback) : self::authLoginAsUserInfo($params, $config, $userInfoCallback);
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
        $sta = new SaeTOAuthV2($config['appKey'], $config['appSecret']);

        $oauth_url = $sta->getAuthorizeURL($redirectUri, 'code', null, null, $scope);
        // 对外跳转url
        return array(
            'redirectServer' => false,
            'url'=>$oauth_url
        );
    }
    protected static function authLoginAsBase($params, $config, Closure $baseInfoCallback){
        // 调整地址
        $redirectUri = empty($params['redirect_uri'])?'':$params['redirect_uri'];
        $uid = null;
        try{
            $sta = new SaeTOAuthV2($config['appKey'], $config['appSecret']);
            $tokenArray = $sta->getAccessToken('code', array(
                'code' => $params['code'],
                'redirect_uri' => $config['authUri']
            ));
            $uid = empty($tokenArray['uid'])?$uid:$tokenArray['uid'];
        }catch (DdvException $e){
            $params['nbauth'] = '1';
            $res = self::authLoginAsUrl($params, $config, false);
            $res['redirectServer'] = true;
            return $res;
        }

        if ($uid){
            $res = $baseInfoCallback(array(
                'uid' => $uid
            ), $tokenArray);
        }else{
            $res = false;
        }

        if ($res!==true){
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
            $uid = null;
            $sta = null;
            $tokenStr = null;
            try{
                $sta = new SaeTOAuthV2($config['appKey'], $config['appSecret']);
                $tokenArray = $sta->getAccessToken('code', array(
                    'code' => $params['code'],
                    'redirect_uri' => $config['authUri']
                ));
                $uid = empty($tokenArray['uid'])?$uid:$tokenArray['uid'];
                if (empty($tokenArray['access_token'])){
                    throw new Exception('access token error', 'ACCESS_TOKEN_ERROR');
                }
                if (empty($tokenArray['uid'])){
                    throw new Exception('uid error', 'UID_ERROR');
                }
                $tokenStr = $tokenArray['access_token'];
            }catch (DdvException $e){
                $params['nbauth'] = '1';
                $res = self::authLoginAsUrl($params, $config, false);
                $res['redirectServer'] = true;
                return $res;
            }

            $resUsersShowData = $sta->get('users/show', array(
                'access_token' => $tokenStr,
                'uid'=>$uid
            ));
            $resData = array(
                'uid'=>$uid,
                'usersShow'=>null,
                'errorMessage'=>null,
                'errorCode'=>null,
                'tokenArray'=>$tokenArray
            );
            if (empty($resUsersShowData) || (!empty($resUsersShowData['error_code'])) || empty($resUsersShowData['id'])){
                $resData['errorMessage'] = $resUsersShowData['error']||'unknow error';
                $resData['errorCode'] = $resUsersShowData['error_code']||'500';
            }else{
                $resData['usersShow'] = $resUsersShowData;
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
