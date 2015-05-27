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
echo 'Automap could not find a '.\Automap\Mgr::typeToString($type)
	.' named \''.$symbol."'\n";
}

//---------------------------
// This is a success handler

function success($entry,$id)
{
$map=\Automap\Mgr::map($id);

echo "Automap loaded "
	.\Automap\Mgr::typeToString($entry['stype'])
	.' \''
	.$entry['symbol']
	.'\' from '
	.\Automap\Mgr::typeToString($entry['ptype'])
	.' '
	.$entry['path']
	."\n";
echo 'Symbol was found in this map: '.$map->path()."\n";
}

//---------------------------
// Register handlers

\Automap\Mgr::registerFailureHandler('failure');
\Automap\Mgr::registerSuccessHandler('success');

$id=\Automap\Mgr::load(__DIR__.'/../hello/auto.map');

//-------

function check_loaded($id)
{
echo "Map ID $id is loaded ? ".(\Automap\Mgr::isActiveID($id) ? 'yes' : 'no')."\n";
}

//-------

class_exists('No_Class1'); // Fails

Message::display('Hello, world (using Automap)'); // Succeeds

class_exists('No_Class2'); // Fails

check_loaded($id);	// Yes

check_loaded(1234);	// No

$map=\Automap\Mgr::map($id);

\Automap\Mgr::unload($id);

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
