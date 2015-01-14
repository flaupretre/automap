<?php

namespace Example;

require(__DIR__.'/../../automap.phk');

\Automap::load('auto.map');

//---------------------------

Message::display('Hello, world (using Automap)');

?>
