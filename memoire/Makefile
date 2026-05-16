# ============================================================
#  Compilation du mémoire — Makefile
# ============================================================
# Usage :
#   make            # compilation complète (latexmk)
#   make quick      # compilation rapide (pdflatex, sans biblio)
#   make clean      # nettoie les fichiers intermédiaires
#   make veryclean  # supprime aussi le PDF final
# ============================================================

MAIN    := memoire
LATEX   := pdflatex
LATEXMK := latexmk
BIBER   := bibtex

.PHONY: all quick clean veryclean view

all:
	$(LATEXMK) -pdf -interaction=nonstopmode -halt-on-error $(MAIN).tex

quick:
	$(LATEX) -interaction=nonstopmode -halt-on-error $(MAIN).tex
	$(LATEX) -interaction=nonstopmode -halt-on-error $(MAIN).tex

withbib:
	$(LATEX) -interaction=nonstopmode $(MAIN).tex
	$(BIBER) $(MAIN)
	$(LATEX) -interaction=nonstopmode $(MAIN).tex
	$(LATEX) -interaction=nonstopmode $(MAIN).tex

clean:
	rm -f *.aux *.log *.out *.toc *.lof *.lot *.bbl *.blg *.fls *.fdb_latexmk *.synctex.gz
	rm -f chapters/*.aux frontmatter/*.aux annexes/*.aux settings/*.aux

veryclean: clean
	rm -f $(MAIN).pdf

view: all
	open $(MAIN).pdf 2>/dev/null || xdg-open $(MAIN).pdf 2>/dev/null
