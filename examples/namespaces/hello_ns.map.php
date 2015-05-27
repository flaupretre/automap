<?php

namespace Example;

require(__DIR__.'/../../automap.phk');

\Automap\Mgr::load('auto.map');

//---------------------------

Message::display('Hello, world (using Automap)');

?>
