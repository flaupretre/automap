#==============================================================================

TARGETS = auto1.map auto2.map
EXTRA_CLEAN = php.log

include ../make.vars
include ../make.common

#-----------------------------------------------------------------------------

.PHONY: all clean test mem_test

all: base
base: test

clean: clean_base

auto1.map :
	$(PHP) -d phk.enable_cli=1 ../automap.phk register -m auto1.map src1
	
auto2.map :
	$(PHP) -d phk.enable_cli=1 ../automap.phk register -m auto2.map src2

test: $(TARGETS)
	$(PHP) -d phk.enable_cli=1 test.php

mem_test: $(TARGETS)
	USE_ZEND_ALLOC=0 valgrind --tool=memcheck --log-file=php.log --leak-check=full \
		--track-origins=yes $(PHP) -d phk.enable_cli=1 test.php

#-----------------------------------------------------------------------------
