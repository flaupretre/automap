#
#==============================================================================

PRODUCT=Automap
TARGETS = $(PRODUCT).phk
SOURCE_DIR = src
SUBDIRS = test
DOC_TYPES = api xref

#-----------------------------

include ./make.vars

.PHONY: all clean_doc clean doc check mem_check

all: $(TARGETS)

$(PRODUCT).phk: $(PRODUCT).psf $(PHK_CREATOR)
	SOURCE_DIR=$(SOURCE_DIR) $(PHP) $(PHK_CREATOR) build $@ $(PRODUCT).psf

clean: clean_doc
	for i in $(SUBDIRS) ; do $(MAKE) -C $$i $@ ; done
	/bin/rm -rf $(TARGETS)
	
check mem_check: all
	$(MAKE) -C test $@

#--- Doc

doc: 
	@for type in $(DOC_TYPES); do \
		$(MAKE) $(PRODUCT)_$$type.phk; \
	done;

clean_doc:
	for type in $(DOC_TYPES); do \
		/bin/rm -f $(PRODUCT)_$$type.phk;\
	done

$(PRODUCT)_api.phk: $(PRODUCT)_api.psf $(PHK_CREATOR)
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	$(PHPDOC) --help | head -1 | grep 'version 2' >/dev/null ;\
	if [ $$? = 0 ] ; then opts='-q --title $(PRODUCT)' ;\
	else opts='-o HTML:frames:DOM/earthli -ti $(PRODUCT)' ; fi;\
	$(PHPDOC) $$opts -d $(SOURCE_DIR) -t $(TMP_DIR) 
	SOURCE_DIR=$(SOURCE_DIR) TMP_DIR=$(TMP_DIR) $(PHP) $(PHK_CREATOR) build $@ $(PRODUCT)_api.psf
	/bin/rm -rf $(TMP_DIR)

# Generating a PDF documentation is available in phpdoc V 1 only

$(PRODUCT)_api.pdf: $(PRODUCT)_api.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	$(PHPDOC) -o PDF:default:default -d $(SOURCE_DIR) \
		-t $(TMP_DIR) -ti "$(PRODUCT) API"
	mv $(TMP_DIR)/documentation.pdf $@
	/bin/rm -rf $(TMP_DIR)

#------ Cross Reference

$(PRODUCT)_xref.phk: $(PRODUCT)_xref.psf $(PRODUCT)_xref.cfg $(PHK_CREATOR) $(PHPXREF_DIR)/phpxref.pl
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	PHPXREF_DIR=$(PHPXREF_DIR) INPUT=$(SOURCE_DIR) OUTPUT=$(TMP_DIR) \
		./xref.sh $(PRODUCT)_xref.cfg
	SOURCE_DIR=$(SOURCE_DIR) TMP_DIR=$(TMP_DIR) $(PHP) $(PHK_CREATOR) build $@ $(PRODUCT)_xref.psf
	/bin/rm -rf $(TMP_DIR)

#-----------------------------------------------------------------------------
