<?php

namespace Example\info {

const TOTO=1;
//const tutu=2;

define('Example\info\tutu',1);

class EnvInfo
{

public static function is_web()
{
return (php_sapi_name()!='cli');
}

} // End of class


function t2()
{
}
}

namespace NS3 {

function t3()
{
echo "Called t3\n";
}
}
?>
