#
#
#==============================================================================

TARGETS = auto.map

include ../make.vars

#-----------------------------------------------------------------------------

.PHONY: all clean

all:  $(TARGETS)

clean:
	/bin/rm -f $(TARGETS)

auto.map : ../Automap.phk
	$(PHP) ../Automap.phk register auto.map . src

#-----------------------------------------------------------------------------
