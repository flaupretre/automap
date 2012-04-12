<?php

//------------------------------------------------------------------

class Message1
{

public static function get($msg)
{
return ((EnvInfo1::is_web()) ? "<h1>$msg</h1>" : "$msg";
}

?>
