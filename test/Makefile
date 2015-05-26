#==============================================================================
# As this Makefile is included in the distrib, it cannot include make templates

TARGETS = auto1.map auto2.map
EXTRA_CLEAN = php.log

PHP = php

#-----------------------------------------------------------------------------

.PHONY: all clean test mem_test

all: test

clean:
	/bin/rm -rf $(TARGETS) $(EXTRA_CLEAN)

auto1.map :
	$(PHP) ../automap.phk register -m auto1.map src1
	
auto2.map :
	$(PHP) ../automap.phk register -m auto2.map src2

test: $(TARGETS)
	$(PHP) test.php

mem_test: $(TARGETS)
	USE_ZEND_ALLOC=0 valgrind --tool=memcheck --log-file=php.log --leak-check=full \
		--track-origins=yes $(PHP) test.php

#-----------------------------------------------------------------------------
