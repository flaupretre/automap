<?php
// Don't use namespaces (would require PHP 5.3+)

class Tester
{

private $tname='<Undef>';
private $tnum=0;
private $cnum=0;
private $ccount=0;
private $errors=array();
private $col=0;

const MAXCOLS=50;

//---------------

public function __construct($name)
{
echo "===== Checking $name =====\n\n";
}

//---------------

public function start($tname)
{
$this->tnum++;
$this->cnum=0;
$this->tname=$tname;
//echo "\n".str_pad($this->tnum,3,' ',STR_PAD_LEFT).'. '.$tname.': ';
}

//---------------

private function display_char($c)
{
$this->col++;
if ($this->col > self::MAXCOLS)
	{
	echo "\n";
	$this->col=0;
	}
echo $c;
}

//---------------

public function check($ctext,$cond)
{
$this->cnum++;
$this->ccount++;

if ($cond)
	{
	self::display_char('.');
	}
else
	{
	self::display_char('F');
	$this->errors[]=array('tname' => $this->tname
		, 'tnum' => $this->tnum
		, 'ctext' => $ctext
		, 'cnum' => $this->cnum);
	}
}

//---------------

public function end()
{
echo "\n\n============================= Summary =========================\n";
echo 'Summary: Tests: '.$this->ccount.' - OK: '.($this->ccount-count($this->errors))
	.' - KO: '.count($this->errors)."\n";
	
if (count($this->errors))
	{
	echo "\n* Failures:\n";

	foreach($this->errors as $e)
		{
		echo $e['tnum'].'.'.$e['cnum'].' - '.$e['tname'].' / '.$e['ctext']."\n";
		}
	}
}

} // End of class
?>
