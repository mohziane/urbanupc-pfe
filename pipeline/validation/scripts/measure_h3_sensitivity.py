"""Analyse de sensibilité H3 — Robustesse face à la calibration des churns.

Limitation L-2 de H3 : les taux de churn par catégorie ont été estimés
à partir de retours d'expérience génériques. Si la calibration est
inexacte, le gap TA(continu) − TA(90j) peut être biaisé.

La présente analyse mesure la **sensibilité du gap** à une variation
multiplicative uniforme des taux de churn :

* `× 0.5` : SI très stable (churn moitié)
* `× 1.0` : configuration de référence
* `× 1.5` : SI plus volatile que prévu
* `× 2.0` : SI fortement volatile (worst case)
* `× 3.0` : extrême — borne supérieure stress

Pour chaque facteur, 100 simulations Monte Carlo sont rejouées avec
les mêmes seeds que la mesure principale, afin d'isoler l'effet de la
variation paramétrique.

Objectif : montrer que la **conclusion H3 (gap ≥ 20 pts) tient sur
une plage large de calibrations**, et identifier le point où H3
basculerait (sanity floor).
"""

from __future__ import annotations

import copy
import json
import sys
from pathlib import Path
from statistics import mean, median, quantiles, stdev

ROOT = Path(__file__).parent.parent
RESULTS_DIR = ROOT / "results"
RESULTS_DIR.mkdir(exist_ok=True)

sys.path.insert(0, str(Path(__file__).parent))
import measure_h3  # noqa: E402


CHURN_FACTORS = [0.5, 1.0, 1.5, 2.0, 3.0]
N_SIMS_PER_FACTOR = 100
BASE_SEED = 42

THRESHOLD = measure_h3.THRESHOLD_TA_GAP  # 0.20


def run_with_churn_factor(factor: float, n_sims: int, base_seed: int) -> dict:
    """Rejoue le Monte Carlo avec un facteur multiplicatif sur les churns."""
    original = copy.deepcopy(measure_h3.CATEGORIES)
    try:
        for cat in measure_h3.CATEGORIES.values():
            cat["churn_per_day"] = min(1.0, cat["churn_per_day"] * factor)
        agg = measure_h3.run_monte_carlo(n_sims, base_seed)
        return agg
    finally:
        for k, v in original.items():
            measure_h3.CATEGORIES[k] = v


def fmt_pct(x: float) -> str:
    return f"{x * 100:.1f} %"


def main() -> int:
    print(f"[h3-sens] Analyse de sensibilité — variation multiplicative des taux de churn")
    print(f"          {N_SIMS_PER_FACTOR} simulations × {len(CHURN_FACTORS)} facteurs\n")

    print(f"  {'Facteur':<10s} {'TA(90j)':>10s} {'Gap moyen':>12s} "
          f"{'IC95% gap':>26s} {'% sim ≥ 20%':>14s}")

    results = []
    for f in CHURN_FACTORS:
        agg = run_with_churn_factor(f, N_SIMS_PER_FACTOR, BASE_SEED)
        ta90 = agg["D_90"]["TA_mean"]
        gap = agg["gap_continu_minus_90j"]
        row = {
            "churn_factor": f,
            "TA_90j_mean": ta90,
            "TA_90j_ci95": list(agg["D_90"]["TA_ci95"]),
            "gap_mean": gap["mean"],
            "gap_median": gap["median"],
            "gap_min": gap["min"],
            "gap_max": gap["max"],
            "gap_ci95": list(gap["ci95"]),
            "pct_above_threshold": gap["pct_above_threshold"],
        }
        results.append(row)
        print(f"  × {f:<8.2f} {fmt_pct(ta90):>10s} {fmt_pct(gap['mean']):>12s} "
              f"[{fmt_pct(gap['ci95'][0]):>6s} ; {fmt_pct(gap['ci95'][1]):>6s}] "
              f"{fmt_pct(gap['pct_above_threshold']):>14s}")

    # Verdict de robustesse
    print()
    above_all = [r for r in results if r["gap_ci95"][0] >= THRESHOLD]
    if len(above_all) == len(results):
        print(f"  ROBUSTESSE : H3 tient sur TOUS les facteurs testés "
              f"(IC95% bas ≥ {THRESHOLD:.0%}).")
    else:
        crit = [r for r in results if r["gap_ci95"][0] < THRESHOLD]
        print(f"  ROBUSTESSE : H3 fragile à facteur(s) "
              f"{[r['churn_factor'] for r in crit]}.")

    out_path = RESULTS_DIR / "h3_sensitivity.json"
    with out_path.open("w", encoding="utf-8") as fh:
        json.dump({"factors": results,
                   "threshold": THRESHOLD,
                   "n_sims_per_factor": N_SIMS_PER_FACTOR}, fh,
                  indent=2, ensure_ascii=False)
    print(f"\n[h3-sens] Résultats : {out_path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
