<?php

namespace Example;

require(__DIR__.'/../../automap.phk');

//---------------------------
// This is a failure handler

function failure($type,$symbol)
{
echo 'Automap could not find a '.\Automap\Mgr::type_to_string($type)
	.' named \''.$symbol."'\n";
}

//---------------------------
// This is a success handler

function success($entry,$id)
{
$stype=$entry['stype'];

echo "Automap loaded "
	.\Automap\Mgr::type_to_string($stype)
	.' \''
	.$entry['symbol']
	.'\' from '
	.\Automap\Mgr::type_to_string($entry['ptype'])
	.' '
	.$entry['path']
	."\n";
echo 'Symbol was found in this map: '.\Automap\Mgr::map($id)->path()."\n";
}

//---------------------------

\Automap\Mgr::register_failure_handler(__NAMESPACE__.'\failure');
\Automap\Mgr::register_success_handler(__NAMESPACE__.'\success');

$id=\Automap\Mgr::load('auto.map');

//-------

function check_loaded($id)
{
echo "Map ID $id is loaded ? ".(\Automap\Mgr::id_is_active($id) ? 'yes' : 'no')."\n";
}

//-------
// Note: class_exists() ignores default namespace

class_exists('No_Class1',1); // Fails
class_exists(__NAMESPACE__.'\No_Class1',1); // Fails

Message::display('Hello, world (using Automap)'); // Succeeds

class_exists('No_Class2'); // Fails

check_loaded($id);	// Yes

check_loaded(1234);	// No

//-- Functions and constants require explicit load requests as long as the
//-- PHP core is not extended to autoload them.

\Automap\Mgr::require_function(__NAMESPACE__.'\dummy1');
dummy1();

\Automap\Mgr::require_constant('NS5\FOO');
var_dump(\NS5\FOO);

\Automap\Mgr::require_function('\NS4\t4');
\NS4\t4();

//--- Unload map

$map=\Automap\Mgr::map($id);
\Automap\Mgr::unload($id);

try
	{
	var_dump($map->path()); // Should throw exception
	}
catch (\Exception $e)
	{
	echo "Exception thrown when accessing an unloaded map with message '".$e->getMessage()."'\n";
	}

check_loaded($id); // No
?>
