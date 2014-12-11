#
#==============================================================================

include ../make.vars

SUBDIRS = handlers hello namespaces

#-----------------------------

.PHONY: all examples clean

all: examples

examples:
	for i in $(SUBDIRS) ; do make -C $$i ; done

clean:
	for i in $(SUBDIRS) ; do make -C $$i $@ ; done

#-----------------------------------------------------------------------------
