#==============================================================================

TARGETS = auto1.map auto2.map

include ../make.vars
include ../make.common

#-----------------------------------------------------------------------------

.PHONY: all clean test mem_test install

all:  $(TARGETS)

clean:
	/bin/rm -f $(TARGETS) php.log

auto1.map :
	$(PHPCMD) ../automap.phk register -m auto1.map src1
	
auto2.map :
	$(PHPCMD) ../automap.phk register -m auto2.map src2

test: all
	$(PHPCMD) test.php

mem_test: all
	valgrind --tool=memcheck --log-file=php.log --leak-check=full \
		--track-origins=yes $(PHP) test.php

#-----------------------------------------------------------------------------
