#
#==============================================================================

PRODUCT=automap
TARGETS = $(PRODUCT).phk
SOURCE_DIR = src
PHK_CREATE = $(PHP) $(PHK_CREATOR) build
EXPAND = build/expand.sh
DISTRIB=$(PRODUCT)-$(SOFTWARE_VERSION)-$(SOFTWARE_RELEASE).tar.gz
TO_CLEAN = $(TARGETS) $(PRODUCT).psf $(DISTRIB) automap

#-----------------------------

include ./make.vars

.PHONY: all clean_doc clean_distrib clean doc distrib test mem_test clean_test \
	examples clean_examples

all: $(TARGETS)

%.phk: %.psf
	SOURCE_DIR=$(SOURCE_DIR) $(PHK_CREATE) $@ $<

%.psf: %.psf.in
	@chmod +x $(EXPAND)
	SOFTWARE_VERSION=$(SOFTWARE_VERSION) SOFTWARE_RELEASE=$(SOFTWARE_RELEASE) \
		$(EXPAND) <$< >$@

clean: clean_doc clean_distrib clean_test clean_examples
	/bin/rm -rf $(TO_CLEAN)

test mem_test: all
	$(MAKE) -C test $@

clean_test:
	make -C test clean

examples: all
	$(MAKE) -C examples $@

clean_examples:
	make -C examples clean

doc:
	$(MAKE) -C doc

clean_doc:
	$(MAKE) -C doc clean

$(DISTRIB): $(TARGETS) doc

distrib: $(DISTRIB)
	chmod +x build/mk_distrib.sh
	BASE=$(PWD) DISTRIB=$(DISTRIB) SOFTWARE_VERSION=$(SOFTWARE_VERSION) \
		SOFTWARE_RELEASE=$(SOFTWARE_RELEASE) build/mk_distrib.sh

clean_distrib:
	/bin/rm -f $<

exe: automap

automap: automap.phk
	cp automap.phk automap
	chmod +x automap
	$(PHP) automap @set_interp '/bin/env php'

#-----------------------------------------------------------------------------
