#==============================================================================

TARGETS = auto.map Automap.phk

include ../make.vars

#-----------------------------------------------------------------------------

.PHONY: all clean

all:  $(TARGETS)

clean:
	/bin/rm -f $(TARGETS)

Automap.phk:$(AUTOMAP_PHK)
	cp $(AUTOMAP_PHK) .

auto.map : Automap.phk
	$(PHP) Automap.phk register auto.map . src

#-----------------------------------------------------------------------------
