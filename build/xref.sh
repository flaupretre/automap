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
#
# $1 = Config file

if [ -z "$PHPXREF_DIR" ] ; then
	echo "PHPXREF_DIR is not defined"
	exit 1
fi

TMP=/tmp/.t$$
/bin/rm -rf $TMP

save_wd=`pwd`
cd $INPUT
SOURCE=`pwd`
cd $save_wd

(
echo "SOURCE=$SOURCE"
echo "OUTPUT=$OUTPUT"
grep -v '^SOURCE=' <$1 | grep -v 'OUTPUT='
) >$TMP

cd $PHPXREF_DIR
perl phpxref.pl -c $TMP

/bin/rm -f $TMP

exit 0
