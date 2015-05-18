
SOURCE_DIR = ../src
BUILD_DIR = ../build

CLASSES_DIR = ../src


TARGETS = api.phk api.pdf xref.phk
EXTRA_CLEAN = api.psf xref.psf

#------

include ../make.vars
include ../make.common

#------

.PHONY: clean

all: base

clean: clean_base

#------ API Documentation
#-- Restrict API documentation to the public API

api.phk: api.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir -p $(TMP_DIR)
	$(PHPDOC) -o HTML:frames:DOM/earthli -ti "$(PRODUCT) API" \
		-d $(SOURCE_DIR) -t $(TMP_DIR)
	$(PHK_BUILD) $@ -d SOURCE=$(TMP_DIR)
	/bin/rm -rf $(TMP_DIR)

#-- PDF

api.pdf: api.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir -p $(TMP_DIR)
	$(PHPDOC) -o PDF:default:default -ti "$(PRODUCT) API" \
		-d $(SOURCE_DIR) -t $(TMP_DIR)
	mv $(TMP_DIR)/documentation.pdf $@
	/bin/rm -rf $(TMP_DIR)

#-- Cross Reference

xref.phk: xref.psf
	/bin/rm -rf $(TMP_DIR)
	mkdir $(TMP_DIR)
	PHPXREF_DIR="$(PHPXREF_DIR)" INPUT="$(SOURCE_DIR)" OUTPUT="$(TMP_DIR)" \
			$(XREF) xref.cfg
	$(PHK_BUILD) $@ -d SOURCE=$(TMP_DIR)
	/bin/rm -rf $(TMP_DIR)
