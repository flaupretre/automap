#
#==============================================================================

SUBDIRS = handlers hello namespaces

include ../make.vars
include ../make.common

all: base
base: examples

.PHONY: all examples clean

clean: clean_base

examples:
	for i in $(SUBDIRS) ; do $(MAKE) -C $$i ; done

#-----------------------------------------------------------------------------
