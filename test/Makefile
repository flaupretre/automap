#==============================================================================

TARGETS = auto1.map auto2.map

include ../make.vars

#-----------------------------------------------------------------------------

.PHONY: all clean check mem_check install

all:  $(TARGETS)

clean:
	/bin/rm -f $(TARGETS) php.log

auto1.map :
	$(PHP) ../Automap.phk register auto1.map . src1
	
auto2.map :
	$(PHP) ../Automap.phk register auto2.map . src2

check: all
	$(PHP) test.php

mem_check: all
	valgrind --tool=memcheck --log-file=php.log --leak-check=full \
		--track-origins=yes $(PHP) test.php

install:
# Do nothing

#-----------------------------------------------------------------------------
