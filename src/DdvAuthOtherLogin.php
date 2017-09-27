<?php
namespace DdvPhp;
use DdvPhp\DdvAuthOtherLogin\Exception;

/**
 * Class Cors
 *
 * Wrapper around PHPMailer
 *
 * @package DdvPhp\DdvAuthOtherLogin
 */
class DdvAuthOtherLogin
{
    private static $configKeys = array(
        'authUri',
        'isAutoTryBaseLogin',
        'isDdvRestfulApi'
    );
    private static $callback = array(

    );
    private static $config = array(
        // 是否需要尝试静默授权
        'isAutoTryBaseLogin'=>true,
        // 是否为ddvRestfulApi
        'isDdvRestfulApi'=>true,
        'wechat_mp'=>array(

        ),
        'wechat_web'=>array(

        ),
        'wechat_app'=>array(

        ),
        'alipay_web'=>array(

        ),
        'alipay_app'=>array(

        ),
        'qq_connect_web'=>array(

        ),
        'weibo_web'=>array(

        )
    );
    /**
     * 设置配置文件
     * @param array $params [请求参数]
     * @return array $r [返回配置参数]
     */
    public static function authLogin($params = array()){
        if (empty($params)){
            $params = self::getParams();
        }
        $type = empty($params['type'])?'':$params['type'];
        if (!(isset(self::$config[$type])&&is_array(self::$config[$type]))){
            throw new Exception('不支持该登录方式', 'NOT_SUPPORT_LOGIN_TYPE');
        }
        $config = array();
        foreach (self::$configKeys as $key){
            $config[$key] = self::$config[$key];
        }
        $config = array_merge($config, self::$config[$type]);
        if (empty(self::$callback)||empty(self::$callback[$type])){
            throw new Exception('没有设置callback', 'NOT_SET_CALLBACK');
        }
        // 判断是否支持该配置
        return self::isHasType($type)::authLogin(
            $params,
            $config,
            self::$callback[$type]['userInfoCallback'],
            self::$callback[$type]['baseInfoCallback']
        );
    }
    /**
     * 设置配置文件
     * @param string $type [配置参数]
     * @param array $config [配置参数]
     * @return DdvAuthOtherLogin self [请求对象]
     */
    public static function setAuthCallBack($type, $config=array(), $userInfoCallback = null, $baseInfoCallback = null){
        self::setConfig($config, $type);
        $callback = &self::$callback;
        if (empty($callback[$type])){
            $callback[$type] = array();
        }
        $callback[$type]['userInfoCallback'] = $userInfoCallback;
        $callback[$type]['baseInfoCallback'] = $baseInfoCallback;
    }
    /**
     * 设置配置文件
     * @param array $config [配置参数]
     * @return DdvAuthOtherLogin self [请求对象]
     */
    public static function setConfig($config = array(), $type=null) {
        if (empty($type)&&is_null($type)){
            if (isset($config)&&is_array($config)){
                foreach ($config as $type => $c){
                    self::setConfig($c, $type);
                }
            }
        }else{
            self::setConfigByType($config, $type);
        }
        return self::class;
    }
    /**
     * 设置配置文件
     * @param array $config [配置参数]
     * @param string $type [参数类型]
     * @return DdvAuthOtherLogin self [请求对象]
     */
    public static function setConfigByType($config = array(), $type=null) {
        // 转换类型
        $type = self::type2type($type);
        // 判断是否支持该配置
        self::isConfigKeyReturnBoolean($type) || self::isHasType($type);
        // 设置配置信息
        self::$config[$type] = $config;
        // 返回类名
        return self::class;
    }
    /**
     * 返回参数
     * @return array $config [参数]
     */
    public static function getParams(){
        $params = array();
        if ((!empty($_POST))&&is_array($_POST)){
            $params = array_merge($params, $_POST);
        }
        if ((!empty($_GET))&&is_array($_GET)){
            $params = array_merge($params, $_GET);
        }
        return $params;
    }
    public static function isConfigKey($key = null){
        if (!self::isConfigKeyReturnBoolean($key)){
            throw new Exception('没有找到该配置key:['.$key.']', 'NOT_FIND_CONFIG_KEY');
        }
    }
    public static function isConfigKeyReturnBoolean($key = null){
        return in_array($key, self::$configKeys);
    }
    /**
     * 设置配置文件
     * @param string $type [参数类型]
     */
    public static function isHasType($type = null){
        if (empty($type)){
            throw new Exception('没有找到该类型['.$type.']', 'NOT_FIND_TYPE');
        }
        $className = '\\DdvPhp\\DdvAuthOtherLogin\\Lib\\'.ucfirst(preg_replace_callback(
            '(\_\w)',
            function ($matches) {
                return strtoupper(substr($matches[0], 1));
            },
            (string)$type
        ));
        if (!class_exists($className)) {
            throw new Exception('没有找到该类型['.$type.']', 'NOT_FIND_TYPE');
        }
        return $className;
    }
    /**
     * 设置配置文件
     * @param string $type [参数类型]
     * @return string $type [参数类型]
     */
    protected static function type2type($type){
        switch ($type){
            case 'weixin_mp':
                $type = 'wechat_mp';
                break;
            case 'weixin_web':
                $type = 'wechat_web';
                break;
            case 'weixin_app':
                $type = 'wechat_app';
                break;
        }
        return $type;
    }
}
