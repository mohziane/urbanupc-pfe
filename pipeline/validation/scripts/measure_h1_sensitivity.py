"""Analyse de sensibilité de la mesure H1.

Évalue la robustesse du résultat M1.2 sous **perturbations contrôlées**
de la classification A/B/C. L'objectif est de répondre à la question :

    "Si un second codeur reclassifiait certaines preuves
    différemment, à quel point le résultat changerait-il ?"

Cinq scénarios de perturbation sont évalués :

* **Baseline** — classification originale.
* **Pessimiste léger / fort** — déplace des items A→B et B→C
  (couverture réelle inférieure à l'estimation).
* **Optimiste léger / fort** — déplace des items C→B et B→A
  (couverture réelle supérieure à l'estimation).
* **Random walk** — 200 perturbations aléatoires de 10 items,
  bornées à un voisinage de la classe d'origine.

Pour chaque scénario, M1.2 est calculée globalement, sur le scope
technique (étapes ANSSI 4, 6, 7, 9) et sur le scope gouvernance
(étapes 1, 2, 3, 5, 8). La plage observée constitue la **bande de
robustesse** du résultat.
"""

from __future__ import annotations

import json
import random
import sys
from collections import Counter
from pathlib import Path
from statistics import mean, median, stdev

import yaml

ROOT = Path(__file__).parent.parent
CATALOG = ROOT / "P_total_catalog.yaml"
RESULTS_DIR = ROOT / "results"
RESULTS_DIR.mkdir(exist_ok=True)


# ─────────────────────────────────────────────────────────────────────
# Métriques (importées par duplication pour autonomie du script)
# ─────────────────────────────────────────────────────────────────────

TECH_STEPS = {4, 6, 7, 9}


def m12(items: list[dict]) -> float:
    if not items:
        return 0.0
    n = len(items)
    nA = sum(1 for it in items if it["class"] == "A")
    nB = sum(1 for it in items if it["class"] == "B")
    return (nA + 0.5 * nB) / n


def metrics_triplet(items: list[dict]) -> dict:
    tech = [it for it in items if it["anssi"] in TECH_STEPS]
    gov = [it for it in items if it["anssi"] not in TECH_STEPS]
    return {
        "global": m12(items),
        "technique": m12(tech),
        "gouvernance": m12(gov),
    }


# ─────────────────────────────────────────────────────────────────────
# Perturbations
# ─────────────────────────────────────────────────────────────────────


def perturb_directed(
    catalog: list[dict],
    n_AB: int = 0,
    n_BA: int = 0,
    n_BC: int = 0,
    n_CB: int = 0,
    seed: int = 42,
) -> list[dict]:
    """Applique des transitions de classes ciblées au catalogue.

    Args:
        n_AB : nombre d'items à déplacer de A vers B (pessimiste).
        n_BA : nombre d'items à déplacer de B vers A (optimiste).
        n_BC : nombre d'items à déplacer de B vers C (pessimiste).
        n_CB : nombre d'items à déplacer de C vers B (optimiste).
        seed : graine du générateur aléatoire.
    """
    rng = random.Random(seed)
    perturbed = [item.copy() for item in catalog]

    pools = {
        "A": [i for i, it in enumerate(perturbed) if it["class"] == "A"],
        "B": [i for i, it in enumerate(perturbed) if it["class"] == "B"],
        "C": [i for i, it in enumerate(perturbed) if it["class"] == "C"],
    }

    def move(src: str, dst: str, n: int) -> None:
        n_actual = min(n, len(pools[src]))
        if n_actual == 0:
            return
        chosen = rng.sample(pools[src], n_actual)
        for idx in chosen:
            perturbed[idx] = perturbed[idx].copy()
            perturbed[idx]["class"] = dst
            pools[src].remove(idx)
            pools[dst].append(idx)

    move("A", "B", n_AB)
    move("B", "A", n_BA)
    move("B", "C", n_BC)
    move("C", "B", n_CB)
    return perturbed


def perturb_random_walk(
    catalog: list[dict], n_moves: int = 10, seed: int = 42
) -> list[dict]:
    """Réalise n_moves transitions aléatoires bornées (A↔B↔C uniquement)."""
    rng = random.Random(seed)
    perturbed = [it.copy() for it in catalog]

    NEIGHBORS = {
        "A": ["B"],         # une preuve auto peut être recodée semi-auto
        "B": ["A", "C"],    # une semi-auto peut basculer dans les deux sens
        "C": ["B"],         # une manuelle peut être recodée semi-auto
    }

    for _ in range(n_moves):
        idx = rng.randrange(len(perturbed))
        old = perturbed[idx]["class"]
        new = rng.choice(NEIGHBORS[old])
        perturbed[idx] = perturbed[idx].copy()
        perturbed[idx]["class"] = new

    return perturbed


# ─────────────────────────────────────────────────────────────────────
# Scénarios
# ─────────────────────────────────────────────────────────────────────


SCENARIOS_DIRECTED = [
    {"name": "Baseline (sans perturbation)", "n_AB": 0, "n_BA": 0, "n_BC": 0, "n_CB": 0},
    {"name": "Pessimiste léger (5 A→B, 3 B→C)", "n_AB": 5, "n_BA": 0, "n_BC": 3, "n_CB": 0},
    {"name": "Pessimiste fort (10 A→B, 5 B→C)", "n_AB": 10, "n_BA": 0, "n_BC": 5, "n_CB": 0},
    {"name": "Optimiste léger (5 C→B, 3 B→A)",  "n_AB": 0, "n_BA": 3, "n_BC": 0, "n_CB": 5},
    {"name": "Optimiste fort (10 C→B, 5 B→A)",  "n_AB": 0, "n_BA": 5, "n_BC": 0, "n_CB": 10},
]

N_RANDOM_TRIALS = 200
N_RANDOM_MOVES = 10


# ─────────────────────────────────────────────────────────────────────
# Programme principal
# ─────────────────────────────────────────────────────────────────────


def fmt_pct(x: float) -> str:
    return f"{x * 100:.1f} %"


def main() -> int:
    with CATALOG.open("r", encoding="utf-8") as fh:
        data = yaml.safe_load(fh)
    catalog = data["catalog"]
    print(f"[sens] Catalogue chargé : {len(catalog)} preuves.")

    results: dict = {"directed": [], "random_walk": {}}

    # ─── Scénarios dirigés ────────────────────────────────────────────
    print("\n[sens] Scénarios dirigés :")
    print(f"  {'Scénario':<40s}  M1.2 glob.   M1.2 tech.   M1.2 gouv.")
    for sc in SCENARIOS_DIRECTED:
        perturbed = perturb_directed(
            catalog,
            n_AB=sc["n_AB"], n_BA=sc["n_BA"],
            n_BC=sc["n_BC"], n_CB=sc["n_CB"], seed=42,
        )
        triplet = metrics_triplet(perturbed)
        results["directed"].append({"scenario": sc["name"], **triplet})
        print(
            f"  {sc['name']:<40s}  {fmt_pct(triplet['global']):>8s}     "
            f"{fmt_pct(triplet['technique']):>8s}     "
            f"{fmt_pct(triplet['gouvernance']):>8s}"
        )

    # ─── Random walk (Monte Carlo) ────────────────────────────────────
    print(f"\n[sens] Random walk : {N_RANDOM_TRIALS} essais × {N_RANDOM_MOVES} transitions")
    glob_samples, tech_samples, gov_samples = [], [], []
    for trial in range(N_RANDOM_TRIALS):
        perturbed = perturb_random_walk(catalog, n_moves=N_RANDOM_MOVES, seed=42 + trial)
        t = metrics_triplet(perturbed)
        glob_samples.append(t["global"])
        tech_samples.append(t["technique"])
        gov_samples.append(t["gouvernance"])

    def summary(samples: list[float]) -> dict:
        return {
            "n": len(samples),
            "min": min(samples),
            "max": max(samples),
            "mean": mean(samples),
            "median": median(samples),
            "std": stdev(samples) if len(samples) > 1 else 0.0,
        }

    results["random_walk"] = {
        "n_trials": N_RANDOM_TRIALS,
        "n_moves": N_RANDOM_MOVES,
        "global": summary(glob_samples),
        "technique": summary(tech_samples),
        "gouvernance": summary(gov_samples),
    }

    for scope, samples in [
        ("Global", glob_samples),
        ("Technique", tech_samples),
        ("Gouvernance", gov_samples),
    ]:
        s = summary(samples)
        print(
            f"  {scope:<12s}  min={fmt_pct(s['min'])} médiane={fmt_pct(s['median'])} "
            f"moyenne={fmt_pct(s['mean'])} max={fmt_pct(s['max'])} σ={s['std']*100:.2f} pts"
        )

    # ─── Robustesse du verdict ────────────────────────────────────────
    print("\n[sens] Robustesse du verdict 'H1 confirmée sur scope technique' :")
    threshold = 0.50  # borne inférieure de l'IC acceptable
    n_above = sum(1 for v in tech_samples if v >= threshold)
    pct_above = n_above / N_RANDOM_TRIALS
    print(
        f"  Sur {N_RANDOM_TRIALS} perturbations, M1.2 technique ≥ {threshold:.0%} "
        f"dans {n_above} cas ({pct_above:.1%})."
    )
    results["robustness"] = {
        "threshold": threshold,
        "pct_tech_above_threshold": pct_above,
        "n_trials_above": n_above,
        "total_trials": N_RANDOM_TRIALS,
    }

    # ─── Sauvegarde ───────────────────────────────────────────────────
    out_path = RESULTS_DIR / "h1_sensitivity.json"
    with out_path.open("w", encoding="utf-8") as fh:
        json.dump(results, fh, indent=2, ensure_ascii=False)
    print(f"\n[sens] Données sauvegardées : {out_path}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
