#
#==============================================================================

PRODUCT=automap
TARGETS = $(PRODUCT).phk
TO_CLEAN = $(TARGETS) $(PRODUCT).psf
SOURCE_DIR = src
PHK_CREATE = $(PHP) $(PHK_CREATOR) build
EXPAND = build/expand.sh

#-----------------------------

include ./make.vars

.PHONY: all clean_doc clean doc test mem_test

all: $(TARGETS)

%.phk: %.psf
	SOURCE_DIR=$(SOURCE_DIR) $(PHK_CREATE) $@ $<

%.psf: %.psf.in
	@chmod +x $(EXPAND)
	SOFTWARE_VERSION=$(SOFTWARE_VERSION) SOFTWARE_RELEASE=$(SOFTWARE_RELEASE) \
		$(EXPAND) <$< >$@

clean: clean_doc
	make -C test clean
	/bin/rm -rf $(TO_CLEAN)
	
test mem_test: all
	$(MAKE) -C test $@

doc:
	$(MAKE) -C doc

clean_doc:
	$(MAKE) -C doc clean

#-----------------------------------------------------------------------------
