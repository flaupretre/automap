#
#==============================================================================

TARGETS = $(PRODUCT).phk
SOURCE_DIR = src
BUILD_DIR = build
EXTRA_CLEAN = $(PRODUCT).psf $(PRODUCT)

#-----------------------------

include ./make.vars
include ./make.common

#-----------------------------

.PHONY: all clean_doc clean_distrib clean doc distrib test mem_test clean_test \
	examples clean_examples

clean: clean_doc clean_distrib clean_test clean_examples
	/bin/rm -rf $(TARGETS) $(EXTRA_CLEAN)

#--- How to build the package

$(PRODUCT).phk: $(PRODUCT).psf
	SOURCE_DIR=$(SOURCE_DIR) $(PHK_BUILD) $@ $<

#--- Tests

test mem_test: all
	$(MAKE) -C test $@

clean_test:
	$(MAKE) -C test clean

#--- Examples

examples: all
	$(MAKE) -C examples $@

clean_examples:
	$(MAKE) -C examples clean

#--- Documentation

doc:
	$(MAKE) -C doc

clean_doc:
	$(MAKE) -C doc clean

#--- How to build distrib

distrib: $(DISTRIB)

$(DISTRIB): $(TARGETS) doc
	BASE=$(PWD) TMP_DIR=$(TMP_DIR) PRODUCT=$(PRODUCT) \
	SOFTWARE_VERSION=$(SOFTWARE_VERSION) \
	SOFTWARE_RELEASE=$(SOFTWARE_RELEASE) $(MK_DISTRIB)

clean_distrib:
	/bin/rm -f $(DISTRIB)

#--- Sync external code - Dev private

sync_external:
	for i in PHO_Display PHO_File PHO_Getopt PHO_Util ; do \
		cp -p ../../../phool/public/src/$$i.php src/classes/external/phool ;\
	done

#-----------------------------------------------------------------------------
