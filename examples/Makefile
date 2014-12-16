#
#==============================================================================

include ../make.vars
include ../make.common

SUBDIRS = handlers hello namespaces

#-----------------------------

.PHONY: all examples clean

all: examples

examples:
	for i in $(SUBDIRS) ; do $(MAKE) -C $$i ; done

clean:
	for i in $(SUBDIRS) ; do $(MAKE) -C $$i $@ ; done

#-----------------------------------------------------------------------------
