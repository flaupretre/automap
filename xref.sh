#
# Set default directory to phpxref install directory and, then, launch phpxref.
# If phpxref is called from another directory, the result does not include
# the style sheets (phpxref bug).
# This wrapper script allows not to set the output path in the configuration file.
#
# Variables :
# INPUT = Directory containing the source files (relative to the directory
#         containing the script)
# OUTPUT = Output dir
# PHPXREF_DIR = phpxref install dir

TMP=/tmp/.t$$
/bin/rm -rf $TMP

cd `dirname $0`
dir=`/bin/pwd`

SOURCE=$INPUT
echo $SOURCE | grep '^/' >/dev/null 2>&1 || SOURCE=$dir/$INPUT

echo "SOURCE=$SOURCE" >$TMP
echo "OUTPUT=$OUTPUT" >>$TMP
grep -v '^SOURCE=' <$1 | grep -v 'OUTPUT=' >>$TMP

cd $PHPXREF_DIR
perl phpxref.pl -c $TMP

/bin/rm -f $TMP

exit 0
