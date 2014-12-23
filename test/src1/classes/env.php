<?php

//------------------------------------------------------------------

class EnvInfo1
{

public static function is_web()
{
return (php_sapi_name()!='cli');
}

} // End of class

//------------------------------------------------------------------
?>
