
include ../make.vars

PRODUCT = automap
TMP_DIR = /tmp/.mk.tmp
SOURCE_DIR = ../src
PUBLIC_API_CLASSES = Automap Automap_Creator Automap_Tools

TARGETS = api.phk api.pdf xref.phk
TO_CLEAN = $(TARGETS) api.psf xref.psf

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
#-- Restrict API documentation to the public API

api.phk: api.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	mkdir $(TMP_DIR)/result
	mkdir $(TMP_DIR)/source
	for i in $(PUBLIC_API_CLASSES) ; do cp ../src/classes/$$i.php $(TMP_DIR)/source ; done
	$(PHPDOC) -o HTML:frames:DOM/earthli -ti "$(PRODUCT) API" -d $(TMP_DIR)/source -t $(TMP_DIR)/result
	$(PHK_CREATE) $@ $< $(TMP_DIR)/result
	chmod +r $@
	/bin/rm -rf $(TMP_DIR)

# PDF

api.pdf: api.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	mkdir $(TMP_DIR)/result
	mkdir $(TMP_DIR)/source
	for i in $(PUBLIC_API_CLASSES) ; do cp ../src/classes/$$i.php $(TMP_DIR)/source ; done
	$(PHPDOC) -o PDF:default:default -ti "$(PRODUCT) API" -d $(TMP_DIR)/source -t $(TMP_DIR)/result
	mv $(TMP_DIR)/result/documentation.pdf $@
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
