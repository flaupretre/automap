#
#==============================================================================

SUBDIRS = handlers hello namespaces

all: examples

include ../make.vars
include ../make.common

.PHONY: all examples clean

clean: clean_base

examples:
	for i in $(SUBDIRS) ; do $(MAKE) -C $$i ; done

#-----------------------------------------------------------------------------
