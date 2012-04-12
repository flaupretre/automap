<?php

//------------------------------------------------------------------

class EnvInfo2
{

public static function is_web()
{
return (php_sapi_name()!='cli');
}

} // End of class

//------------------------------------------------------------------
?>
