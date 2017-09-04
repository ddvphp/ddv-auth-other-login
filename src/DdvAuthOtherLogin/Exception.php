<?php
namespace DdvPhp\DdvAuthOtherLogin;

class Exception extends \DdvPhp\DdvException\Error
{
  // 魔术方法
  public function __construct( $message = 'DdvAuthOtherLogin Error', $errorId = 'DDV_AUTH_OTHER_LOGIN_ERROR' , $code = '400', $errorData = array() )
  {
    parent::__construct( $message , $errorId , $code, $errorData );
  }
}
