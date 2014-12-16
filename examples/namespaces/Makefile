#==============================================================================

include ../../make.vars
include ../../make.common

TARGETS = auto.map

#-----------------------------------------------------------------------------

.PHONY: all clean

all:  $(TARGETS)

clean:
	/bin/rm -f $(TARGETS)

auto.map :
	$(PHPCMD) ../../automap.phk register src

#-----------------------------------------------------------------------------
