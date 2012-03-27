<?php

namespace Example;

class Message
{

public static function display($msg)
{
if (info\EnvInfo::is_web()) echo "<h1>$msg</h1>";
else echo "$msg\n";
}

} // End of class

namespace\info\EnvInfo::is_web();

class class2 {}

?>
