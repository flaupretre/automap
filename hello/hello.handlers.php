<?php

namespace Example;


if (!class_exists('\Automap',false)) require('Automap.phk');

//---------------------------

function fail($type,$symbol)
{
echo "Automap could not find ".$symbol.' '.\Automap::type_to_string($type)."\n";
}

//---------------------------

function succeed($type,$symbol,$map)
{
$v=$map->get_symbol($type,$symbol);

echo "Automap loaded "
	.\Automap::type_to_string($type)
	.' '
	.$symbol
	.' from '
	.\Automap::type_to_string($v['ptype'])
	.' '
	.$v['path']
	."\n";
}

//---------------------------

\Automap::register_failure_handler(__NAMESPACE__.'\\fail');
\Automap::register_success_handler(__NAMESPACE__.'\\succeed');

$mnt=\Automap::mount('auto.map');

//-------

function check_mounted($mnt)
{
echo "$mnt is mounted ? ".(\Automap::is_mounted($mnt) ? 'yes' : 'no')."\n";
}

//-------

class_exists('No_Class1'); // Fails

Message::display('Hello, world (using Automap)'); // Succeeds

class_exists('No_Class2'); // Fails

check_mounted($mnt);

check_mounted('foo');

$map=\Automap::instance($mnt);

\Automap::umount($mnt);

try
	{
	var_dump($map->path());
	}
catch (\Exception $e)
	{
	echo "Exception thrown after umount - OK (".$e->getMessage().")\n";
	}

check_mounted($mnt);
?>
