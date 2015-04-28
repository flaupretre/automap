#==============================================================================

TARGETS = auto1.map auto2.map
EXTRA_CLEAN = php.log

include ../make.vars
include ../make.common

#-----------------------------------------------------------------------------

.PHONY: all clean test mem_test

all: base

clean: clean_base

auto1.map :
	$(PHPCMD) ../automap.phk register -m auto1.map src1
	
auto2.map :
	$(PHPCMD) ../automap.phk register -m auto2.map src2

test: base
	$(PHPCMD) test.php

mem_test: base
	USE_ZEND_ALLOC=0 valgrind --tool=memcheck --log-file=php.log --leak-check=full \
		--track-origins=yes $(PHP) test.php

#-----------------------------------------------------------------------------
