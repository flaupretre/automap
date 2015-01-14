<?php
// This test shows how success and failure handlers can be used.
// It does not use namespaces.
//
// It uses the map from the 'hello' subdir (run 'make' there to build it).
//----------------------------------------------------------------------------

require(__DIR__.'/../../automap.phk'); // Load Automap runtime

//---------------------------
// This is a failure handler

function failure($type,$symbol)
{
echo 'Automap could not find a '.Automap::type_to_string($type)
	.' named \''.$symbol."'\n";
}

//---------------------------
// This is a success handler

function success($entry,$map)
{
$stype=$entry['stype'];

echo "Automap loaded "
	.Automap::type_to_string($stype)
	.' \''
	.$entry['symbol']
	.'\' from '
	.Automap::type_to_string($entry['ptype'])
	.' '
	.$entry['path']
	."\n";
echo 'Symbol was found in this map: '.$map->path()."\n";
}

//---------------------------
// Register handlers

Automap::register_failure_handler('failure');
Automap::register_success_handler('success');

$id=Automap::load(__DIR__.'/../hello/auto.map');

//-------

function check_loaded($id)
{
echo "Map ID $id is loaded ? ".(Automap::id_is_active($id) ? 'yes' : 'no')."\n";
}

//-------

class_exists('No_Class1'); // Fails

Message::display('Hello, world (using Automap)'); // Succeeds

class_exists('No_Class2'); // Fails

check_loaded($id);	// Yes

check_loaded(1234);	// No

$map=Automap::instance($id);

Automap::unload($id);

try
	{
	var_dump($map->path());
	}
catch (Exception $e)
	{
	echo "Exception thrown when accessing an unloaded map with message '".$e->getMessage()."'\n";
	}

check_loaded($id); // No
?>
