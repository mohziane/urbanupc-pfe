"""Point d'entrée CLI du pipeline HOMO-CI."""

from __future__ import annotations

import logging
import sys
from datetime import datetime, timezone
from pathlib import Path

import click
from rich.console import Console
from rich.logging import RichHandler
from rich.table import Table

from . import __version__
from .diff import diff, load_snapshot, snapshot_current, summarize
from .render import DossierRenderer
from .sign import create_snapshot, verify_chain
from .storage import EvidenceStore
from .watchers.base import BaseWatcher, WatcherResult

console = Console()


def _setup_logging(level: str) -> None:
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(message)s",
        handlers=[RichHandler(console=console, rich_tracebacks=True, markup=True)],
    )


@click.group()
@click.version_option(__version__, prog_name="homo-ci")
@click.option("--log-level", default="INFO", show_default=True)
@click.option(
    "--root",
    type=click.Path(path_type=Path),
    default=Path(".homo-ci"),
    show_default=True,
    help="Répertoire racine du pipeline (evidence store + snapshots).",
)
@click.pass_context
def main(ctx: click.Context, log_level: str, root: Path) -> None:
    """HOMO-CI — Pipeline d'homologation continue."""
    _setup_logging(log_level)
    ctx.ensure_object(dict)
    ctx.obj["root"] = root
    ctx.obj["store"] = EvidenceStore(root / "evidence")


@main.command()
@click.option(
    "--include",
    "watchers_filter",
    multiple=True,
    default=("nsg", "cve"),
    help="Watchers à exécuter (par défaut : nsg cve).",
)
@click.pass_context
def collect(ctx: click.Context, watchers_filter: tuple[str, ...]) -> None:
    """Exécute les watchers sélectionnés."""
    store: EvidenceStore = ctx.obj["store"]
    watchers = _select_watchers(store, watchers_filter)
    results: list[WatcherResult] = []
    for w in watchers:
        results.append(w.run())
    _display_results(results)


@main.command(name="diff")
@click.option(
    "--against",
    type=click.Path(path_type=Path),
    help="Snapshot JSONL de référence (par défaut : snapshot précédent).",
)
@click.pass_context
def cmd_diff(ctx: click.Context, against: Path | None) -> None:
    """Calcule la différence avec un snapshot de référence."""
    store: EvidenceStore = ctx.obj["store"]
    root: Path = ctx.obj["root"]
    snapshots_dir = root / "snapshots"

    if against is None:
        # Détecte le dernier snapshot dans le répertoire.
        files = sorted(snapshots_dir.glob("snapshot-*.jsonl"))
        against = files[-1] if files else None

    if against is None or not Path(against).exists():
        console.print(
            "[yellow]Aucun snapshot de référence trouvé — premier run, "
            "tout est CREATED.[/yellow]"
        )
        previous: dict = {}
    else:
        previous = load_snapshot(Path(against))

    # Snapshot courant en mémoire
    current = {(e.source.value, e.ref): e for e in store.list_all()}

    changes = diff(previous, current)
    summary = summarize(changes)

    console.print(f"[bold]Changements détectés vs {against}[/bold]")
    console.print(
        f"  [green]created  : {summary.created}[/green]\n"
        f"  [yellow]updated  : {summary.updated}[/yellow]\n"
        f"  [red]deleted  : {summary.deleted}[/red]"
    )
    for ch in changes[:20]:
        console.print(f"  • {ch.type.value:8s} [{ch.category.value}] {ch.ref}")
    if len(changes) > 20:
        console.print(f"  … (+{len(changes)-20} autres)")


@main.command()
@click.option("--version", "version_str", required=True, help="Numéro de version (ex. V1.0).")
@click.option(
    "--templates",
    type=click.Path(path_type=Path),
    default=Path("templates"),
    show_default=True,
)
@click.option(
    "--out",
    type=click.Path(path_type=Path),
    default=Path("build/dossier.md"),
    show_default=True,
)
@click.pass_context
def render(ctx: click.Context, version_str: str, templates: Path, out: Path) -> None:
    """Rend le dossier d'homologation en Markdown."""
    store: EvidenceStore = ctx.obj["store"]
    renderer = DossierRenderer(store, templates)
    md = renderer.render(version=version_str)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(md, encoding="utf-8")
    console.print(f"[green]✓[/green] Markdown généré : {out} ({len(md)} caractères)")


@main.command()
@click.option("--version", "version_str", required=True)
@click.option(
    "--pdf",
    type=click.Path(path_type=Path),
    required=True,
    help="Chemin du PDF généré à signer.",
)
@click.pass_context
def sign(ctx: click.Context, version_str: str, pdf: Path) -> None:
    """Signe le PDF et persiste un DossierSnapshot."""
    store: EvidenceStore = ctx.obj["store"]
    root: Path = ctx.obj["root"]

    # Calcule la summary depuis le dernier snapshot.
    snapshots_dir = root / "snapshots"
    snapshots_dir.mkdir(parents=True, exist_ok=True)
    snap_files = sorted(snapshots_dir.glob("snapshot-*.jsonl"))
    last_snap = snap_files[-1] if snap_files else None

    previous = load_snapshot(last_snap) if last_snap else {}
    current = {(e.source.value, e.ref): e for e in store.list_all()}
    summary = summarize(diff(previous, current))

    snap = create_snapshot(
        store=store,
        pdf_path=Path(pdf),
        version=version_str,
        change_summary=summary,
    )
    # Persiste le snapshot courant pour servir de référence au prochain diff.
    snapshot_path = snapshots_dir / f"snapshot-{snap.id}.jsonl"
    snapshot_current(store, snapshot_path)
    console.print(
        f"[green]✓[/green] Dossier signé. SHA-256 = {snap.pdf_hash}\n"
        f"  parent_hash = {snap.parent_hash or '<root>'}\n"
        f"  signature   = {snap.signature}"
    )


@main.command(name="verify")
@click.pass_context
def cmd_verify(ctx: click.Context) -> None:
    """Vérifie la cohérence de la hash chain."""
    store: EvidenceStore = ctx.obj["store"]
    if verify_chain(store):
        console.print("[green]✓ Hash chain cohérente.[/green]")
    else:
        console.print("[red]✗ Hash chain rompue ! Voir les logs.[/red]")
        sys.exit(1)


@main.command()
@click.pass_context
def status(ctx: click.Context) -> None:
    """Affiche un état synthétique du store et des snapshots."""
    store: EvidenceStore = ctx.obj["store"]
    total = store.count()
    snapshots = store.list_snapshots()

    table = Table(title="HOMO-CI — État du pipeline", show_header=False)
    table.add_column("Indicateur")
    table.add_column("Valeur")
    table.add_row("Preuves stockées", str(total))
    table.add_row("Snapshots produits", str(len(snapshots)))
    if snapshots:
        last = snapshots[0]
        table.add_row("Dernière version", last.version)
        table.add_row("Généré le", last.generated_at.astimezone(timezone.utc).isoformat())
        table.add_row("PDF hash (12)", last.pdf_hash[:12])
    console.print(table)


def _select_watchers(store: EvidenceStore, filters: tuple[str, ...]) -> list[BaseWatcher]:
    """Sélectionne les watchers à instancier."""
    selected: list[BaseWatcher] = []
    for f in filters:
        if f == "nsg":
            from .watchers.wc_nsg import NSGWatcher

            selected.append(NSGWatcher(store))
        elif f == "cve":
            from .watchers.wc_cve import CVEWatcher

            selected.append(CVEWatcher(store))
        else:
            console.print(f"[yellow]Watcher inconnu : {f}[/yellow]")
    return selected


def _display_results(results: list[WatcherResult]) -> None:
    table = Table(title="Résultats des watchers", show_lines=False)
    table.add_column("Watcher")
    table.add_column("Durée (s)", justify="right")
    table.add_column("Collectées", justify="right")
    table.add_column("Nouvelles", justify="right")
    table.add_column("Inchangées", justify="right")
    table.add_column("Erreurs", justify="right")
    for r in results:
        table.add_row(
            r.source.value,
            f"{r.duration_s:.2f}",
            str(r.evidence_collected),
            f"[green]{r.evidence_new}[/green]",
            str(r.evidence_unchanged),
            f"[red]{len(r.errors)}[/red]" if r.errors else "0",
        )
    console.print(table)


if __name__ == "__main__":
    main()
