#!/bin/sh
# Must be run via 'make distrib'

error_exit()
{
echo "**ERROR** $*"
exit 1
}

#-------------------- Main -----------------

SUBDIR=${PRODUCT}-${SOFTWARE_VERSION}-${SOFTWARE_RELEASE}

#---------

[ -z "$TMP_DIR" ] && error_exit "TMP_DIR not set. This script must not be run directly. Instead, run 'make distrib' in the base directory"

/bin/rm -rf $TMP_DIR
mkdir -p $TMP_DIR || error_exit "Cannot create tmp dir at $TMP_DIR"
cd $TMP_DIR || error_exit "Cannot cd to $TMP_DIR"

mkdir $SUBDIR
cd $SUBDIR
mkdir doc

for i in automap.phk examples test README.md doc/api.phk doc/api.pdf \
	doc/xref.phk
	do
		cp -rp $BASE/$i ./$i
done

#----------------------------
# Build tgz file

cd ..
tar cf - ./$SUBDIR | gzip --best >$BASE/$SUBDIR.tgz
echo "Created file $SUBDIR.tgz"

#-----
# Cleanup

cd $BASE
/bin/rm -rf $TMP_DIR

#=========================================================================
