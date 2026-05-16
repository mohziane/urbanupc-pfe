"""Analyse de sensibilité H2 — Effet de la fréquence de régénération.

La régénération du dossier d'homologation est le **levier
architectural majeur** du pipeline HOMO-CI. La présente analyse
mesure l'impact d'une variation de cette fréquence sur les trois
métriques M2.1, M2.2, M2.3.

Six configurations sont évaluées :

* `weekly`         : régénération hebdomadaire (cycle audit traditionnel)
* `nightly`        : nightly à 22:00 UTC (configuration de référence)
* `every_12h`      : deux fois par jour
* `every_6h`       : quatre fois par jour
* `hourly`         : toutes les heures
* `every_15min`    : régénération à haute fréquence

Pour chaque configuration, M2.1, M2.2 et M2.3 sont calculées sur
le même calendrier d'injections (seed=42) pour permettre une
comparaison à *injections constantes*.

Objectif : démontrer que la **fréquence de régénération est le
paramètre dominant** de la propagation, et fournir au RSSI les
*coûts/bénéfices* opérationnels de chaque choix.
"""

from __future__ import annotations

import copy
import json
import random
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path
from statistics import mean, quantiles

import yaml

ROOT = Path(__file__).parent.parent
PROTOCOL = ROOT / "injection_protocol.yaml"
RESULTS_DIR = ROOT / "results"
RESULTS_DIR.mkdir(exist_ok=True)


# Importe les fonctions clés de measure_h2 sans duplication.
sys.path.insert(0, str(Path(__file__).parent))
from measure_h2 import (  # noqa: E402
    expected_delta_hours,
    generate_schedule,
)


# ─────────────────────────────────────────────────────────────────────
# Configurations de régénération
# ─────────────────────────────────────────────────────────────────────


CONFIGS = [
    {
        "name": "weekly (cycle audit traditionnel)",
        "regen_type": "weekly",
        "interval_h": 168,
        "runtime_min": 20,
    },
    {
        "name": "nightly à 22:00 UTC (référence)",
        "regen_type": "nightly",
        "interval_h": 24,
        "runtime_min": 20,
    },
    {
        "name": "every 12h",
        "regen_type": "interval",
        "interval_h": 12,
        "runtime_min": 20,
    },
    {
        "name": "every 6h",
        "regen_type": "interval",
        "interval_h": 6,
        "runtime_min": 20,
    },
    {
        "name": "hourly",
        "regen_type": "interval",
        "interval_h": 1,
        "runtime_min": 5,
    },
    {
        "name": "every 15 min (real-time)",
        "regen_type": "interval",
        "interval_h": 0.25,
        "runtime_min": 1,
    },
]


def make_protocol_variant(base_protocol: dict, config: dict) -> dict:
    """Construit une variante du protocole avec une fréquence de régénération
    différente. On utilise une astuce simple : on encode l'intervalle
    de régénération dans un format que `expected_delta_hours` peut consommer.
    """
    protocol = copy.deepcopy(base_protocol)
    # On ré-encode la régénération comme un "polling" avec interval_h
    protocol["pipeline_model"]["dossier_regen"] = {
        "type": config["regen_type"],
        "interval_h": config["interval_h"],
        "hour_utc": 22,  # ancre pour nightly/weekly
        "pipeline_runtime_min": config["runtime_min"],
    }
    return protocol


def next_dossier_regen_variant(t_detect: datetime, model: dict) -> datetime:
    """Calcule la prochaine régénération selon la configuration variable."""
    regen = model["dossier_regen"]
    runtime = timedelta(minutes=regen["pipeline_runtime_min"])
    interval_h = regen.get("interval_h", 24)

    if regen["type"] == "nightly":
        hour = regen["hour_utc"]
        scheduled = t_detect.replace(hour=hour, minute=0, second=0, microsecond=0)
        if scheduled <= t_detect:
            scheduled = scheduled + timedelta(days=1)
        return scheduled + runtime

    if regen["type"] == "weekly":
        # Hebdomadaire chaque dimanche à hour_utc
        hour = regen["hour_utc"]
        days_until_sunday = (6 - t_detect.weekday()) % 7
        scheduled = (t_detect + timedelta(days=days_until_sunday)).replace(
            hour=hour, minute=0, second=0, microsecond=0
        )
        if scheduled <= t_detect:
            scheduled = scheduled + timedelta(days=7)
        return scheduled + runtime

    # interval (toutes les X heures)
    interval = timedelta(hours=interval_h)
    epoch = datetime(2026, 6, 1, 0, 0, 0, tzinfo=timezone.utc)
    seconds_since = (t_detect - epoch).total_seconds()
    interval_s = interval.total_seconds()
    seconds_to_next = interval_s - (seconds_since % interval_s)
    return t_detect + timedelta(seconds=seconds_to_next) + runtime


def expected_delta_hours_variant(
    category: str, t_inj: datetime, protocol: dict, rng: random.Random
) -> float:
    """Version qui utilise la régénération variable."""
    # On utilise next_watcher_run depuis measure_h2, mais on remplace
    # next_dossier_regen.
    from measure_h2 import next_watcher_run

    cadence = protocol["pipeline_model"]["watchers"][category]
    t_detect = next_watcher_run(cadence, t_inj, rng)
    t_dossier = next_dossier_regen_variant(t_detect, protocol["pipeline_model"])
    return (t_dossier - t_inj).total_seconds() / 3600.0


# ─────────────────────────────────────────────────────────────────────
# Programme principal
# ─────────────────────────────────────────────────────────────────────


def run_config(base_protocol: dict, config: dict) -> dict:
    """Exécute la simulation pour une configuration de régénération donnée."""
    protocol = make_protocol_variant(base_protocol, config)
    seed = base_protocol["meta"]["seed"]
    n_events = base_protocol["meta"]["n_events"]
    window_days = base_protocol["meta"]["window_days"]

    schedule = generate_schedule(seed=seed, n_events=n_events, window_days=window_days)
    rng = random.Random(seed)
    deltas = []
    for evt in schedule:
        d = expected_delta_hours_variant(evt["category"], evt["t_inj"], protocol, rng)
        deltas.append(d)

    return {
        "config": config["name"],
        "regen_interval_h": config["interval_h"],
        "M2_1": mean(deltas),
        "M2_2_P95": quantiles(deltas, n=20)[18],
        "M2_3_pct_under_24h": sum(1 for d in deltas if d <= 24) / len(deltas),
        "max": max(deltas),
        "min": min(deltas),
    }


def fmt_h(x: float) -> str:
    if x < 1:
        return f"{x * 60:.0f} min"
    if x < 48:
        return f"{x:.1f} h"
    return f"{x / 24:.1f} j"


def fmt_pct(x: float) -> str:
    return f"{x * 100:.1f} %"


def main() -> int:
    with PROTOCOL.open("r", encoding="utf-8") as fh:
        base = yaml.safe_load(fh)

    print(f"[h2-sens] Analyse de sensibilité sur la fréquence de régénération")
    print(f"          Calendrier d'injections constant (seed={base['meta']['seed']})\n")
    print(f"  {'Configuration':<40s}  {'Intervalle':<10s}  {'M2.1':>10s}  "
          f"{'P95':>10s}  {'<24h':>8s}  {'max':>10s}")
    results = []
    for cfg in CONFIGS:
        r = run_config(base, cfg)
        results.append(r)
        print(f"  {cfg['name']:<40s}  {fmt_h(cfg['interval_h']):<10s}  "
              f"{fmt_h(r['M2_1']):>10s}  {fmt_h(r['M2_2_P95']):>10s}  "
              f"{fmt_pct(r['M2_3_pct_under_24h']):>8s}  {fmt_h(r['max']):>10s}")

    out_path = RESULTS_DIR / "h2_sensitivity.json"
    with out_path.open("w", encoding="utf-8") as fh:
        json.dump({"configs": results}, fh, indent=2, ensure_ascii=False)
    print(f"\n[h2-sens] Résultats : {out_path}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
