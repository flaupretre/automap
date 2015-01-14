#
#==============================================================================

SUBDIRS = handlers hello namespaces

include ../make.vars
include ../make.common

#-----------------------------

.PHONY: all examples clean

all: examples

clean: clean_base

examples:
	for i in $(SUBDIRS) ; do $(MAKE) -C $$i ; done

#-----------------------------------------------------------------------------
