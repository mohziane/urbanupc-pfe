# EBIOS RM analysis — pandoc + weasyprint
DIR := $(dir $(realpath $(lastword $(MAKEFILE_LIST))))
SRC := $(sort $(wildcard $(DIR)content/*.md))
STYLE := $(DIR)styles/ebios.css
META := $(DIR)metadata.yaml
BUILD := $(DIR)build
NAME := UrbanUpC-EBIOS-RM

.PHONY: all pdf html docx clean stats

all: pdf

$(BUILD):
	@mkdir -p $(BUILD)

$(BUILD)/$(NAME).html: $(SRC) $(STYLE) $(META) | $(BUILD)
	@pandoc \
	  --metadata-file=$(META) \
	  --from=markdown+raw_html+yaml_metadata_block+pipe_tables+fenced_code_blocks+definition_lists+footnotes \
	  --to=html5 --standalone --section-divs \
	  --css=$(STYLE) -o $@ $(SRC)
	@echo "[ebios] HTML → $@"

html: $(BUILD)/$(NAME).html

pdf: $(BUILD)/$(NAME).html
	@weasyprint $(BUILD)/$(NAME).html $(BUILD)/$(NAME).pdf 2>&1 | grep -vE "^WARNING:|FontConfig" || true
	@ls -lh $(BUILD)/$(NAME).pdf

docx: $(SRC) $(META) | $(BUILD)
	@pandoc --metadata-file=$(META) \
	  --from=markdown+raw_html+yaml_metadata_block+pipe_tables+fenced_code_blocks+definition_lists+footnotes \
	  --to=docx -o $(BUILD)/$(NAME).docx $(SRC)
	@echo "[ebios] DOCX → $(BUILD)/$(NAME).docx"

stats:
	@for f in $(SRC); do printf "%-45s %5d words\n" "$$(basename $$f)" "$$(wc -w < $$f)"; done
	@printf "%-45s %5d words\n" "TOTAL" "$$(cat $(SRC) | wc -w)"

clean:
	@rm -rf $(BUILD)
