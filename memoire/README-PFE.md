# Mémoire de PFE — sources LaTeX

Sources du mémoire et PDF compilé.

## Compilation

```bash
cd memoire
pdflatex memoire.tex
bibtex memoire
pdflatex memoire.tex
pdflatex memoire.tex
open memoire.pdf
```

Ou simplement :

```bash
make
```

## Structure

```
memoire/
├── memoire.tex             Document principal (parts, inputs)
├── settings/preamble.tex   Packages, styles, couleurs ANSSI
├── frontmatter/            Couverture, remerciements, résumé, sigles
├── chapters/               9 chapitres (intro, état de l'art, lab, outil, résultats, etc.)
├── annexes/                A : dossier, B : code, C : données
├── figures/                Schémas et captures
└── bibliographie.bib       Références (ANSSI, ISO, articles)
```

## Plan en 5 parties

1. **Contexte** — Introduction, état de l'existant.
2. **Le lab UrbanUpC** — Architecture, applications, SOC.
3. **L'outil HOMO-CI** — Conception, implémentation.
4. **Ce qu'on a mesuré** — Couverture, fraîcheur, précision, coût.
5. **Discussion et suite** — Limites, perspectives, conclusion.
