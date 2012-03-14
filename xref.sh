#
# Appelle phpxref en se mettant dans le repertoire d'install de phpxref.
# Si on l'appelle a partir d'un autre repertoire, le resultat n'inclut pas les
# feuilles de style.
# Ce wrapper permet de ne pas mettre le repertoire en dur dans le fichier de
# config
# Variables :
# INPUT = Repertoire des sources (relatif par rapport au repertoire du script)
# OUTPUT = repertoire resultat
# PHPXREF_DIR = repertoire d'install de phpxref

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
