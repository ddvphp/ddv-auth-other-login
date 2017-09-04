<?php
namespace DdvPhp;

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
     * @param array $config [配置参数]
     * @return DdvAuthOtherLogin $this [请求对象]
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
    public static function setConfigByType($config = array(), $type=null) {
        $c = &self::$config;
        switch (self::type2type($type)){
            case 'wechat_mp':
                $c['wechat_mp'] = $config;
                break;
            case 'wechat_web':
                $c['wechat_web'] = $config;
                break;
            case 'wechat_app':
                $c['wechat_app'] = $config;
                break;
            case 'alipay_web':
                $c['alipay_web'] = $config;
                break;
            case 'alipay_app':
                $c['alipay_app'] = $config;
                break;
        }
    }
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
    public static function getLoginInfo(){
        return self::$config;
    }
}
