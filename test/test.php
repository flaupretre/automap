<?php

define('MAP1','auto1.map');
define('MAP1_SYMCOUNT',7);

define('MAP2','auto2.map');

//------------------------------------------------------------------------

require dirname(__FILE__).'/Tester.php';

$extension_present=extension_loaded('automap');
$t=$GLOBALS['t']=new Tester('Automap runtime ('.($extension_present ? 'with' : 'whithout')
	.' PECL accelerator)');

//------------------------------------------------------------------------
$t->start('Include Automap.phk');

$res=require(dirname(__FILE__).'/../Automap.phk');
$t->check('include() returns NULL',is_string($res));

//---------------------------------
$t->start('Mount maps');

$mnt1=Automap::mount(MAP1);
$t->check('mount() returns string (1)',is_string($mnt1));

$map1=Automap::instance($mnt1);
$t->check('map object is instance of Automap class (1)',($map1 instanceof Automap));

$mnt2=Automap::mount(MAP2);
$t->check('mount() returns string (2)',is_string($mnt2));

$map2=Automap::instance($mnt2);
$t->check('map object is instance of Automap class (2)',($map2 instanceof Automap));

//---------------------------------
$t->start('Versions');

$t->check('version() returns string',is_string($map1->version()));
$t->check('min_version() returns string',is_string($map1->min_version()));
$t->check('min_map_version() returns string',is_string($map1->min_map_version()));

//---------------------------------
$t->start('Mount points');

$t->check('$mnt1 is_mounted() is true',Automap::is_mounted($mnt1));

$t->check('<bad> is not mounted()',!Automap::is_mounted('<bad>'));

$ex=false;
try { Automap::validate($mnt2); }
catch (Exception $e) { $ex=true; }
$t->check('validate($mnt2) does not throw exceptions', !$ex);

$ex=false;
try { Automap::validate('no_name'); }
catch (Exception $e) { $ex=true; }
$t->check('validate(<wrong string>) throws exceptions', $ex);

//---------------------------------
$t->start('Mount/umount');

Automap::umount($mnt1);

$t->check('is_mounted() false on umounted ID',!Automap::is_mounted($mnt1));
$t->check('Unmounted instance is not valid',!$map1->is_valid());

$ex=false;
try { Automap::validate($mnt1); }
catch (Exception $e) { $ex=true; }
$t->check('validate on umounted ID thows exception',$ex);

$saved_mnt1=$mnt1;

$mnt1=Automap::mount(MAP1,null,'mnt_id');
$t->check('Explicit mnt ID',$mnt1==='mnt_id');

$map11=Automap::instance($mnt1);

Automap::umount($mnt1);

$mnt1=Automap::mount(MAP1);
$t->check('Map remounted with same ID',$mnt1===$saved_mnt1);

$ex=false;
try { $map11->options(); }
catch (Exception $e) { $ex=true; }
$t->check('Accessing an unmounted instance throws exception',$ex);

$ex=false;
try { $map1->options(); }
catch (Exception $e) { $ex=true; }
$t->check('Accessing a remounted instance throws exception',$ex);

$t->check('Remounted instance is not valid',!$map1->is_valid());

$map1=Automap::instance($mnt1);

//---------------------------------
$t->start('Instance methods');

$t->check('Map path',$map1->path()===dirname(__FILE__).'/'.MAP1);

$t->check('Base directory',$map1->base_dir()===dirname(__FILE__).'/');

$t->check('Mount ID',$map1->mnt()===$mnt1);

$t->check('Flags',$map1->flags()===0);

$a=$map1->options();
$t->check('Options = array()',((is_array($a))&&(count($a)==0)));

$t->check('Getting non-existant option returns null',is_null($map1->option('foo')));

$t->check('symbol_count()',$map1->symbol_count()===MAP1_SYMCOUNT);

$t->check('using_accelerator()',Automap::using_accelerator()===$extension_present);

//---------------------------------
$t->start('symbols() method');

$syms=$map1->symbols();
$t->check('symbols() returns array',is_array($syms));
$t->check('symbols() / array count',count($syms)==MAP1_SYMCOUNT);
foreach ($syms as $sym)
	{
	$t->check('symbols(): element is array',is_array($sym));
	$t->check('symbols(): element contains <stype> index',isset($sym['stype']));
	$t->check('symbols(): element contains <symbol> index',isset($sym['symbol']));
	$t->check('symbols(): element contains <ptype> index',isset($sym['ptype']));
	$t->check('symbols(): element contains <path> index',isset($sym['path']));
	$t->check('symbols(): checking path',file_exists($sym['path']));
	$t->check('symbols(): element contains <rpath> index',isset($sym['rpath']));
	$t->check('symbols(): checking rpath',file_exists($sym['rpath']));
	}

$t->check('check() returns 0 errors',Automap_Tools::check($map1)===0);

$t->check('get_symbol() returns false on non existing symbol',$map1->get_symbol(Automap::T_CLASS,'nosuchclass')===false);

//---------------------------------
$t->start('Explicit get methods');

$t->check('get_constant(<wrong name>) returns false',Automap::get_constant('invalid_name')===false);

$t->check('get_constant(<valid name>) returns true', Automap::get_constant('CONST11')===true);
$t->check('get_constant() defines constant',defined('CONST11'));

$t->check('get_function(<wrong name>) returns false',Automap::get_function('foo')===false);

$t->check('get_function(<valid name>) returns true', Automap::get_function('func12')===true);
$t->check('get_function() defines function',function_exists('func12'));

$t->check('get_class(<wrong name>) returns false',Automap::get_class('foo')===false);

$t->check('get_class(<valid name>) returns true', Automap::get_class('c13')===true);
$t->check('get_class() defines class',class_exists('c13',false));

//----------
$t->start('Explicit require methods');

$ex=false;
try { Automap::require_constant('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('require_constant(<wrong name>) throws exception',$ex);

$t->check('require_constant(<valid name>) returns true', Automap::require_constant('CONST21')===true);
$t->check('require_constant() defines constant',defined('CONST21'));

$ex=false;
try { Automap::require_function('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('require_function(<wrong name>) throws exception',$ex);

$t->check('require_function(<valid name>) returns true', Automap::require_function('func22')===true);
$t->check('require_function() defines function',function_exists('func22'));

$ex=false;
try { Automap::require_class('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('require_class(<wrong name>) throws exception',$ex);

$t->check('require_class(<valid name>) returns true', Automap::require_class('c23')===true);
$t->check('require_class() defines class',class_exists('c23',false));

//---------------------------------
$t->start('Autoloading');

$t->check('Intra-map', Message2::get('foo')==='FOO2');
$t->check('Inter-map', Message2x::get('foo')==='FOO1');

//---------------------------------
$t->start('Success handler');

//------

function success_func($stype,$sname,$map)
{
$t=$GLOBALS['t'];

$t->check('Handler receives the right map',$map->symbol_count()===MAP1_SYMCOUNT);
$t->check('Handler receives the right symbol type',$stype===Automap::T_CLASS);
$t->check('Handler receives the right symbol name',$sname==='c14');

$sym=$map->get_symbol($stype,$sname);

$t->check('Success handler: get_symbol(): returned element is array',is_array($sym));
$t->check('Success handler: get_symbol() returns correct symbol type',$sym['stype']===Automap::T_CLASS);
$t->check('Success handler: get_symbol() returns correct symbol name',$sym['symbol']==='c14');
$t->check('Success handler: get_symbol() returns correct path type',$sym['ptype']===Automap::F_SCRIPT);
$t->check('Success handler: get_symbol(): returned correct relative path',$sym['rpath']==='src1/file14.php');
$t->check('Success handler: get_symbol(): checking file existence (absolute path)',file_exists($sym['path']));
$t->check('Success handler: get_symbol(): checking file existence (relative path)',file_exists($sym['rpath']));

$GLOBALS['success_handler_called']=true;
}

//------

Automap::register_success_handler('success_func');
$GLOBALS['success_handler_called']=false;
$GLOBALS['failure_handler_called']=false;
$t->check('Load class through class_exists()',class_exists('c14',1));
$t->check('Success handler was called',$GLOBALS['success_handler_called']);
$t->check('Failure handler was not called',!$GLOBALS['failure_handler_called']);

//---------------------------------
$t->start('Failure handler');

//------

function failure_func($stype,$sname)
{
$t=$GLOBALS['t'];

$t->check('Handler receives the right symbol type',$stype===Automap::T_CLASS);
$t->check('Handler receives the right symbol name',$sname==='Inexistent_Class');

$GLOBALS['failure_handler_called']=true;
}

Automap::register_failure_handler('failure_func');
$GLOBALS['success_handler_called']=false;
$GLOBALS['failure_handler_called']=false;
$t->check('Load invalid class through class_exists() fails',!class_exists('Inexistent_Class',1));
$t->check('Failure handler was called',$GLOBALS['failure_handler_called']);
$t->check('Success handler was not called',!$GLOBALS['success_handler_called']);

//----------------

$t->end();
?>
