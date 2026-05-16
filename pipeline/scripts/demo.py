"""Démonstration end-to-end du pipeline HOMO-CI (sans dépendances Azure).

Ce script :

1. Crée un EvidenceStore temporaire.
2. Y injecte un jeu de preuves représentatives (NSG + CVE).
3. Lance le Renderer pour produire le Markdown.
4. Compile le Markdown en PDF via pandoc + weasyprint.
5. Crée un DossierSnapshot signé par hash chain.
6. Modifie une preuve (simule un changement) puis régénère.
7. Vérifie la cohérence de la chaîne.

Usage :
    python scripts/demo.py
"""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path
from typing import Any

# Permet l'exécution depuis le répertoire pipeline/ sans installation.
ROOT = Path(__file__).parent.parent
sys.path.insert(0, str(ROOT))

from homo_ci.diff import diff, snapshot_current, summarize  # noqa: E402
from homo_ci.models import (  # noqa: E402
    Category,
    Evidence,
    Materiality,
    Scope,
    SourceType,
    Weight,
)
from homo_ci.render import DossierRenderer  # noqa: E402
from homo_ci.sign import create_snapshot, verify_chain  # noqa: E402
from homo_ci.storage import EvidenceStore  # noqa: E402


def inject_nsg(store: EvidenceStore, rules: list[dict[str, Any]]) -> None:
    """Injecte un lot de règles NSG fictives."""
    scope = Scope(anssi_steps=(4, 6), iso_controls=("8.20",))
    for rule in rules:
        ev = Evidence.from_payload(
            source=SourceType.NSG,
            category=Category.NSG,
            ref=f"{rule['nsg']}/{rule['name']}",
            payload=rule,
            scope=scope,
            weight=Weight.AUTO,
            materiality=Materiality.HIGH if "internet" in str(rule.get("source_addresses", "")).lower() else Materiality.MEDIUM,
        )
        store.upsert(ev)


def inject_cve(store: EvidenceStore, cves: list[dict[str, Any]]) -> None:
    """Injecte un lot de findings CVE fictifs."""
    scope = Scope(anssi_steps=(6, 7), iso_controls=("8.8",), asvs_controls=("V14.2.1",))
    for cve in cves:
        ev = Evidence.from_payload(
            source=SourceType.CVE,
            category=Category.CVE,
            ref=f"{cve['image']}::{cve['vulnerability_id']}::{cve['package']}",
            payload=cve,
            scope=scope,
            weight=Weight.AUTO,
            materiality=Materiality.HIGH if cve["severity"] in ("HIGH", "CRITICAL") else Materiality.MEDIUM,
        )
        store.upsert(ev)


def compile_pdf(md_path: Path, pdf_path: Path, css_path: Path) -> None:
    """Compile Markdown → HTML → PDF via pandoc + weasyprint."""
    # Étape 1 : pandoc Markdown → HTML
    html_path = pdf_path.with_suffix(".html")
    subprocess.run(
        [
            "pandoc",
            "--from", "markdown+pipe_tables+raw_html",
            "--to", "html5",
            "--standalone",
            "--css", str(css_path),
            "--output", str(html_path),
            str(md_path),
        ],
        check=True,
    )
    # Étape 2 : weasyprint HTML → PDF
    subprocess.run(
        ["weasyprint", str(html_path), str(pdf_path)],
        check=True,
    )


def run_cycle(store: EvidenceStore, version: str, build_dir: Path) -> Path:
    """Exécute un cycle complet : render → compile → sign."""
    print(f"\n=== Cycle {version} ===")

    # 1. Render — avec contexte statique YAML
    renderer = DossierRenderer(
        store,
        templates_dir=ROOT / "templates",
        context_file=ROOT / "config" / "dossier_context.yaml",
    )
    md_content = renderer.render(version=version)
    md_path = build_dir / f"dossier-{version}.md"
    build_dir.mkdir(parents=True, exist_ok=True)
    md_path.write_text(md_content, encoding="utf-8")
    print(f"  → Markdown : {md_path} ({len(md_content)} caractères)")

    # 2. Compile
    pdf_path = build_dir / f"dossier-{version}.pdf"
    compile_pdf(md_path, pdf_path, ROOT / "styles" / "dossier.css")
    print(f"  → PDF      : {pdf_path} ({pdf_path.stat().st_size:,} octets)")

    # 3. Sign
    snapshots_dir = store.root.parent / "snapshots"
    last_files = sorted(snapshots_dir.glob("snapshot-*.jsonl")) if snapshots_dir.exists() else []
    last_snap = last_files[-1] if last_files else None

    from homo_ci.diff import load_snapshot

    previous = load_snapshot(last_snap) if last_snap else {}
    current = {(e.source.value, e.ref): e for e in store.list_all()}
    change_summary = summarize(diff(previous, current))

    snap = create_snapshot(store, pdf_path, version=version, change_summary=change_summary)
    snapshots_dir.mkdir(parents=True, exist_ok=True)
    snapshot_current(store, snapshots_dir / f"snapshot-{snap.id}.jsonl")

    print(f"  → SHA-256  : {snap.pdf_hash[:16]}…")
    print(f"  → Parent   : {(snap.parent_hash or '<root>')[:16]}{'…' if snap.parent_hash else ''}")
    print(f"  → Signature: {(snap.signature or '')[:16]}…")
    print(f"  → Diff     : +{change_summary.created} ~{change_summary.updated} -{change_summary.deleted}")
    return pdf_path


def main() -> None:
    build_dir = ROOT / "build"
    store_root = ROOT / ".homo-ci"

    # Reset propre pour la démo.
    if store_root.exists():
        import shutil
        shutil.rmtree(store_root)
    if build_dir.exists():
        import shutil
        shutil.rmtree(build_dir)

    store = EvidenceStore(store_root / "evidence")

    # ─── Cycle 1 : état initial ──────────────────────────────────
    initial_nsg = [
        {"nsg": "nsg-dmz", "name": "Allow-HTTP", "priority": 100, "direction": "Inbound",
         "access": "Allow", "protocol": "Tcp",
         "source_addresses": ["Internet"], "source_ports": ["*"],
         "destination_addresses": ["*"], "destination_ports": ["80", "443"]},
        {"nsg": "nsg-dmz", "name": "Allow-SSH-Admin", "priority": 110, "direction": "Inbound",
         "access": "Allow", "protocol": "Tcp",
         "source_addresses": ["46.193.66.88"], "source_ports": ["*"],
         "destination_addresses": ["*"], "destination_ports": ["22"]},
        {"nsg": "nsg-dmz", "name": "Deny-DMZ-to-LAN", "priority": 200, "direction": "Outbound",
         "access": "Deny", "protocol": "*",
         "source_addresses": ["*"], "source_ports": ["*"],
         "destination_addresses": ["10.0.2.0/24"], "destination_ports": ["*"]},
        {"nsg": "nsg-lan", "name": "Allow-RDP-Admin", "priority": 100, "direction": "Inbound",
         "access": "Allow", "protocol": "Tcp",
         "source_addresses": ["46.193.66.88"], "source_ports": ["*"],
         "destination_addresses": ["*"], "destination_ports": ["3389"]},
    ]
    initial_cves = [
        {"image": "internal-apps/maformation:latest", "vulnerability_id": "CVE-2024-21672",
         "package": "lodash", "installed_version": "4.17.20", "fixed_version": "4.17.21",
         "severity": "HIGH", "cvss_score": 7.4, "title": "Prototype pollution",
         "published": "2024-06-12"},
        {"image": "internal-apps/macandidature:latest", "vulnerability_id": "CVE-2024-1234",
         "package": "express", "installed_version": "4.18.0", "fixed_version": "4.21.0",
         "severity": "MEDIUM", "cvss_score": 5.3, "title": "Open redirect",
         "published": "2024-08-05"},
    ]
    inject_nsg(store, initial_nsg)
    inject_cve(store, initial_cves)
    print(f"\n[demo] {store.count()} preuves injectées initialement.")
    run_cycle(store, "V1.0", build_dir)

    # ─── Cycle 2 : modifications simulant l'évolution du SI ──────
    # (a) nouvelle règle NSG ajoutée par l'équipe ops (matériel)
    # (b) règle SSH supprimée
    # (c) CVE résolue par patch
    print("\n[demo] Simulation de l'évolution du SI…")
    print("        + Allow-Web01-to-DC01-LDAP (nouvelle règle, prio 150)")
    print("        - Allow-SSH-Admin (révoquée)")
    print("        ~ CVE-2024-21672 (patchée vers 4.17.21)")
    inject_nsg(store, [
        {"nsg": "nsg-dmz", "name": "Allow-Web01-to-DC01-LDAP", "priority": 150,
         "direction": "Outbound", "access": "Allow", "protocol": "Tcp",
         "source_addresses": ["10.0.1.10"], "source_ports": ["*"],
         "destination_addresses": ["10.0.2.10"], "destination_ports": ["88", "389", "636"]},
    ])
    store.delete_by_ref(SourceType.NSG, "nsg-dmz/Allow-SSH-Admin")
    inject_cve(store, [
        {"image": "internal-apps/maformation:latest", "vulnerability_id": "CVE-2024-21672",
         "package": "lodash", "installed_version": "4.17.21", "fixed_version": None,
         "severity": "LOW", "cvss_score": 0, "title": "Patched",
         "published": "2024-06-12"},
    ])
    print(f"[demo] {store.count()} preuves après évolution.")
    run_cycle(store, "V1.1", build_dir)

    # ─── Vérification de la chaîne ───────────────────────────────
    print("\n=== Vérification de la hash chain ===")
    ok = verify_chain(store)
    print(f"  Cohérence  : {'✓ OK' if ok else '✗ ROMPUE'}")
    snapshots = store.list_snapshots()
    print(f"  Snapshots  : {len(snapshots)}")
    for snap in reversed(snapshots):
        print(
            f"    {snap.version}  hash={snap.pdf_hash[:12]}  "
            f"parent={(snap.parent_hash or '<root>')[:12]}  "
            f"changes=+{snap.change_summary.created} "
            f"~{snap.change_summary.updated} -{snap.change_summary.deleted}"
        )

    print("\n[demo] ✓ Pipeline opérationnel.")


if __name__ == "__main__":
    main()
