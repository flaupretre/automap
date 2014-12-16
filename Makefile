#
#==============================================================================

include ./make.vars
include ./make.common

#-----------------------------

TARGETS = $(PRODUCT).phk
SOURCE_DIR = src
BUILD_DIR = build
DISTRIB=$(PRODUCT)-$(SOFTWARE_VERSION)-$(SOFTWARE_RELEASE).tgz
EXTRA_CLEAN = $(PRODUCT).psf $(PRODUCT)

#-----------------------------

.PHONY: all clean_doc clean_distrib clean doc distrib test mem_test clean_test \
	examples clean_examples

all: $(TARGETS)

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

$(DISTRIB): $(TARGETS) doc

distrib: $(DISTRIB)
	BASE=$(PWD) TMP_DIR=$(TMP_DIR) PRODUCT=$(PRODUCT) \
	SOFTWARE_VERSION=$(SOFTWARE_VERSION) \
	SOFTWARE_RELEASE=$(SOFTWARE_RELEASE) $(MK_DISTRIB)

clean_distrib:
	/bin/rm -f $(DISTRIB)

#--- How to transform the package into a shell executable

exe: $(PRODUCT)

$(PRODUCT): $(PRODUCT).phk
	cp $(PRODUCT).phk $(PRODUCT)
	chmod +x $(PRODUCT)
	$(PHPCMD) $(PRODUCT) @set_interp '/bin/env php'

#--- Sync external code - Dev private

sync_external:
	for i in PHO_Display PHO_File PHO_Getopt PHO_Util ; do \
		cp -p ../../../phool/public/src/$$i.php src/classes/external/phool ;\
	done

#-----------------------------------------------------------------------------
