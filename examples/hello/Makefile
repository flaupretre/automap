#==============================================================================

TARGETS = auto.map
AUTOMAP_PKG = ../../automap.phk

include ../../make.vars
include ../../make.common

#-----------------------------------------------------------------------------

.PHONY: all clean

all: base

clean: clean_base

auto.map :
	$(PHPCMD) $(AUTOMAP_PKG) register src

#-----------------------------------------------------------------------------
