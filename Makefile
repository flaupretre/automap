#
#==============================================================================

PRODUCT=Automap

TARGETS = $(PRODUCT).phk

SOURCE_DIR = src

SUBDIRS = test

NO_FILTER = true
FILTER_SOURCE=$(SOURCE_DIR)

include ./make.vars
include ./make.common

.PHONY: check mem_check

check mem_check: all
	$(MAKE) -C test $@

#-----------------------------------------------------------------------------
