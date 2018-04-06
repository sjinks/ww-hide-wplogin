PHP_FRONT      := ../inc/Plugin.php
PHP_BACK       := $(filter-out $(PHP_FRONT),$(wildcard ../inc/*.php) $(wildcard ../views/*.php))
DOMAIN_FRONT   := wwhwl
DOMAIN_BACK    := wwhwla
PO_FRONT_FILES := $(wildcard $(DOMAIN_FRONT)-*.po)
PO_BACK_FILES  := $(wildcard $(DOMAIN_BACK)-*.po)
MO_FRONT_FILES := $(patsubst %.po,%.mo,$(PO_FRONT_FILES))
MO_BACK_FILES  := $(patsubst %.po,%.mo,$(PO_BACK_FILES))

POT_FRONT      := $(DOMAIN_FRONT).pot
POT_BACK       := $(DOMAIN_BACK).pot

XGETTEXT       := xgettext
MSGFMT         := msgfmt
MSGMERGE       := msgmerge

XGETTEXT_OPTIONS := --language=php \
	--keyword=__ \
	--keyword=_e \
	--keyword=_x:1,2c \
	--keyword=_ex:1,2c \
	--keyword=_n:1,2 \
	--keyword=_nx:1,2,4c \
	--keyword=_n_noop:1,2 \
	--keyword=_nx_noop:1,2,3c \
	--keyword=esc_html__ \
	--keyword=esc_html_e \
	--keyword=esc_html_x:1,2c \
	--keyword=esc_attr__ \
	--keyword=esc_attr_e \
	--keyword=esc_attr_x:1,2c \
	--from-code=UTF-8 --omit-header

.PHONY: all clean

all: $(POT_FRONT) $(POT_BACK) $(MO_FRONT_FILES) $(MO_BACK_FILES)

$(MO_FRONT_FILES): $(PO_FRONT_FILES)
$(MO_BACK_FILES):  $(PO_BACK_FILES)

%.mo: %.po
	$(MSGFMT) -o$@ $<

$(PO_FRONT_FILES): %: $(POT_FRONT)
	$(MSGMERGE) -U "$@" $(POT_FRONT)
	touch "$@"

$(PO_BACK_FILES): %: $(POT_BACK)
	$(MSGMERGE) -U "$@" $(POT_BACK)
	touch "$@"

$(POT_FRONT): $(PHP_FRONT)
	$(XGETTEXT) --default-domain=$(DOMAIN_FRONT) $(XGETTEXT_OPTIONS) $(PHP_FRONT) --output="$@"

$(POT_BACK): $(PHP_BACK)
	$(XGETTEXT) --default-domain=$(DOMAIN_BACK) $(XGETTEXT_OPTIONS) $(PHP_BACK) --output="$@"

clean:
	-rm -f $(POT_FRONT) $(POT_BACK) $(MO_FRONT_FILES) $(MO_BACK_FILES)
