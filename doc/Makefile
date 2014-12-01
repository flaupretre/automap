
include ../make.vars

PRODUCT = automap
TMP_DIR = /tmp/.mk.tmp
SOURCE_DIR = ../src

TARGETS = api.phk xref.phk
TO_CLEAN = $(TARGETS) api.pdf api.psf xref.psf

PHK_CREATE = $(PHP) $(PHK_CREATOR) build
EXPAND = ../build/expand.sh
XREF_SCRIPT = ../build/xref.sh

#------

.PHONY: clean

all: $(TARGETS)

clean:
	/bin/rm -f $(TO_CLEAN)

%.psf: %.psf.in
	chmod +x $(EXPAND)
	SOFTWARE_VERSION=$(SOFTWARE_VERSION) SOFTWARE_RELEASE=$(SOFTWARE_RELEASE) \
		$(EXPAND) <$< >$@

#------ API Documentation

api.phk: api.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	$(PHPDOC) --help | head -1 | grep 'version 2' >/dev/null ;\
	if [ $$? = 0 ] ; then opts='-q --title $(PRODUCT)' ;\
	else opts='-o HTML:frames:DOM/earthli -ti $(PRODUCT)' ; fi;\
	$(PHPDOC) $$opts -d $(SOURCE_DIR) -t $(TMP_DIR) 
	$(PHK_CREATE) $@ $< $(TMP_DIR)
	chmod +r $@
	/bin/rm -rf $(TMP_DIR)

# Generating a PDF documentation is available in phpdoc V 1 only

api.pdf: api.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	$(PHPDOC) -o PDF:default:default -d $(SOURCE_DIR) \
		-t $(TMP_DIR) -ti "$(PRODUCT) API"
	mv $(TMP_DIR)/documentation.pdf $@
	chmod +r $@
	/bin/rm -rf $(TMP_DIR)

#------ Cross Reference

xref.phk: xref.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	chmod +x $(XREF_SCRIPT)
	PHPXREF_DIR="$(PHPXREF_DIR)" INPUT="$(SOURCE_DIR)" OUTPUT="$(TMP_DIR)" \
			$(XREF_SCRIPT) xref.cfg
	$(PHK_CREATE) $@ $< $(TMP_DIR)
	chmod +r $@
	/bin/rm -rf $(TMP_DIR)
