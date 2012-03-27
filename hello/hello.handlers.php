<?php

namespace Example;


if (!class_exists('\Automap',false)) require('Automap.phk');

//---------------------------

function fail($key)
{
echo "Automap could not find "
	.\Automap::get_type_string(\Automap::get_type_from_key($key))
	.' '
	.\Automap::get_symbol_from_key($key)
	."\n";
}

//---------------------------

function succeed($key,$mnt,$value)
{
echo "Automap loaded "
	.\Automap::get_type_string(\Automap::get_type_from_key($key))
	.' '
	.\Automap::get_symbol_from_key($key)
	.' from '
	.\Automap::get_type_string(\Automap::get_type_from_key($value))
	.' '
	.\Automap::instance($mnt)->base_dir()
	.\Automap::get_symbol_from_key($value)
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
