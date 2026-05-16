# HOMO-CI — Orchestration locale
#
# Usage :
#   make install         Installe les dépendances Python
#   make test            Tests unitaires
#   make pipeline        Cycle complet : collect → render → compile → sign
#   make verify          Vérifie la cohérence de la hash chain
#   make clean           Supprime build/ et .homo-ci/

SHELL := /bin/bash
PY    := python3
ROOT  := $(shell pwd)
VERSION ?= V$(shell date +%Y%m%d-%H%M%S)
BUILD := build
RUN   := PYTHONPATH=$(ROOT) $(PY) -m homo_ci.cli

.PHONY: help install test lint pipeline collect render compile sign verify clean status

help:
	@grep -E '^[a-zA-Z_-]+:.*?##' $(MAKEFILE_LIST) | awk 'BEGIN {FS=":.*?## "} {printf "%-15s %s\n",$$1,$$2}'

install: ## Installe les dépendances Python
	$(PY) -m pip install -r requirements.txt

test: ## Tests unitaires
	$(PY) -m pytest homo_ci/tests -v

lint: ## Lint (ruff + mypy)
	$(PY) -m ruff check homo_ci/
	$(PY) -m mypy homo_ci/

# ─── Pipeline cycle complet ───────────────────────────────────────────

pipeline: collect render compile sign verify ## Cycle complet
	@echo "[pipeline] ✓ Cycle complet : $(BUILD)/dossier-$(VERSION).pdf"

collect: ## Lance les watchers
	$(RUN) collect --include nsg --include cve

render: ## Rend le Markdown du dossier
	@mkdir -p $(BUILD)
	$(RUN) render --version $(VERSION) --out $(BUILD)/dossier-$(VERSION).md

compile: render ## Compile Markdown → PDF
	weasyprint --base-url $(BUILD) <(\
		pandoc --from markdown+pipe_tables+raw_html \
		       --to html5 \
		       --standalone \
		       --css $(ROOT)/styles/dossier.css \
		       $(BUILD)/dossier-$(VERSION).md \
	) $(BUILD)/dossier-$(VERSION).pdf
	@echo "[pipeline] PDF : $(BUILD)/dossier-$(VERSION).pdf"

sign: ## Calcule hash chain + DossierSnapshot
	$(RUN) sign --version $(VERSION) --pdf $(BUILD)/dossier-$(VERSION).pdf

verify: ## Vérifie la cohérence de la hash chain
	$(RUN) verify

status: ## Affiche un état synthétique
	$(RUN) status

clean: ## Supprime les artefacts
	rm -rf $(BUILD) .homo-ci
