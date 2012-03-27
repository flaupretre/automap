<?php

namespace example;

use NS3 as example3;

if (!class_exists('\Automap',false)) require('Automap.phk');

\Automap::mount('auto.map');

//---------------------------

Message::display('Hello, world (using Automap)');

$c=INFO\t2();

$a=INFO\TOTO;
var_dump($b=\ExaMPle\inFo\tutu);

example3\t3();

?>
