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
    private static $config = array(
        'wechat_mp'=>array(

        ),
        'wechat_web'=>array(

        ),
        'wechat_app'=>array(

        ),
        'alipay_web'=>array(

        ),
        'alipay_app'=>array(

        )
    );
    /**
     * 设置配置文件
     * @param array $params [请求参数]
     * @return array $r [返回配置参数]
     */
    public static function getLoginInfo($params = array()){
        if (empty($params)){
            $params = self::getParams();
        }
        // 判断是否支持该配置
        return self::isHasType(empty($params['type'])?'':$params['type'])::getLoginInfo($params, self::$config);
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
        self::isHasType($type);
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
    /**
     * 设置配置文件
     * @param string $type [参数类型]
     */
    public static function isHasType($type = null){
        if (empty($type)){
            throw new Exception('没有找到该类型', 'NOT_FIND_TYPE');
        }
        $className = '\\DdvPhp\\DdvAuthOtherLogin\\Lib\\'.ucfirst(preg_replace_callback(
            '(\_\w)',
            function ($matches) {
                return strtoupper(substr($matches[0], 1));
            },
            (string)$type
        ));;
        if (!class_exists($className)) {
            throw new Exception('没有找到该类型', 'NOT_FIND_TYPE');
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
