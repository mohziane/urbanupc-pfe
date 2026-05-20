"""Couche 5 — Sceller le dossier.

Le scellement combine deux mécanismes complémentaires :

1. **Intégrité chaînée (interne).** Chaque DossierSnapshot porte le
   SHA-256 du dossier précédent (``parent_hash``), formant une
   *hash chain* auditable localement. C'est une preuve d'antériorité
   **relative** : on prouve que V_{n+1} a été produit *après* V_n,
   mais une chaîne purement locale ne dit rien de la date réelle.

2. **Horodatage RFC 3161 (externe).** Le SHA-256 du PDF est envoyé
   à une *Time Stamping Authority* (TSA) publique conforme RFC 3161,
   qui contre-signe le couple ``(hash, timestamp)`` avec son
   certificat. Le jeton TSR (Time Stamp Response) délivré constitue
   une preuve **absolue** d'antériorité, opposable à un tiers tant
   que la TSA et son CA sont reconnus.

   .. note::

      La TSA utilisée par défaut est *FreeTSA.org*. C'est un service
      RFC 3161 réel (certificat X.509, horodatage signé) mais **non
      qualifié** au sens du règlement eIDAS. Pour une homologation
      réelle, il faut un service qualifié (ex. *Universign*,
      *DocuSign*, *Lex Persona*). L'implémentation est identique —
      seules l'URL de la TSA et les certificats à charger changent.

Le code fait appel à ``openssl ts`` (RFC 3161) et ``curl`` (envoi
de la requête HTTP) car ces outils sont universellement disponibles
sur les plateformes cibles, sans dépendance Python supplémentaire.
"""

from __future__ import annotations

import hashlib
import logging
import os
import re
import shutil
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from uuid import uuid4

from . import ca as ca_module
from .models import ChangeSummary, DossierSnapshot
from .storage import EvidenceStore

logger = logging.getLogger(__name__)


# Configuration par défaut (surchargeable par variables d'environnement).
_DEFAULT_TSA_URL = "https://freetsa.org/tsr"
_DEFAULT_CA_FILE = Path(".homo-ci/tsa/freetsa-cacert.pem")
_DEFAULT_TSA_CERT = Path(".homo-ci/tsa/freetsa-tsacert.pem")


def hash_file(path: Path) -> str:
    """SHA-256 d'un fichier en streaming (mémoire bornée)."""
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(64 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


# ─── RFC 3161 — interaction TSA ──────────────────────────────────────


def _resolve_path(p: str | Path | None, default: Path) -> Path:
    """Résout un chemin, relatif au cwd si non absolu."""
    if not p:
        return Path.cwd() / default
    pp = Path(p)
    return pp if pp.is_absolute() else (Path.cwd() / pp)


def request_timestamp(pdf_path: Path, tsr_path: Path, tsa_url: str | None = None) -> dict | None:
    """Sollicite un horodatage RFC 3161 auprès d'une TSA tierce.

    Args:
        pdf_path: Fichier PDF à horodater.
        tsr_path: Chemin où sauver le jeton TSR retourné.
        tsa_url: URL de la TSA (par défaut FreeTSA.org).

    Returns:
        ``{"provider": ..., "timestamp": datetime, "serial": ...}`` si
        succès, ``None`` si l'horodatage a échoué (réseau, TSA, openssl
        absent…). L'échec n'est jamais bloquant pour le pipeline : on
        signe sans horodatage et on journalise.
    """
    if shutil.which("openssl") is None or shutil.which("curl") is None:
        logger.warning("openssl ou curl absents — horodatage RFC 3161 désactivé.")
        return None

    tsa_url = tsa_url or os.environ.get("HOMO_CI_TSA_URL", _DEFAULT_TSA_URL)

    tsq_path = tsr_path.with_suffix(".tsq")
    tsr_path.parent.mkdir(parents=True, exist_ok=True)

    # Étape 1 : générer la requête TSQ (SHA-256 du PDF + politique).
    rc = subprocess.run(
        [
            "openssl", "ts", "-query",
            "-data", str(pdf_path),
            "-sha256", "-no_nonce", "-cert",
            "-out", str(tsq_path),
        ],
        capture_output=True, text=True, timeout=30, check=False,
    )
    if rc.returncode != 0:
        logger.error("openssl ts -query a échoué : %s", rc.stderr.strip()[:200])
        return None

    # Étape 2 : POST vers la TSA.
    rc = subprocess.run(
        [
            "curl", "--silent", "--show-error", "--fail",
            "--max-time", "30",
            "-H", "Content-Type: application/timestamp-query",
            "--data-binary", f"@{tsq_path}",
            tsa_url,
            "-o", str(tsr_path),
        ],
        capture_output=True, text=True, timeout=45, check=False,
    )
    if rc.returncode != 0:
        logger.error("Requête TSA %s a échoué : %s", tsa_url, rc.stderr.strip()[:200])
        return None

    if not tsr_path.exists() or tsr_path.stat().st_size < 200:
        logger.error("Jeton TSR vide ou trop petit (%s).", tsr_path)
        return None

    # Étape 3 : extraire le timestamp et le serial.
    rc = subprocess.run(
        ["openssl", "ts", "-reply", "-in", str(tsr_path), "-text"],
        capture_output=True, text=True, timeout=10, check=False,
    )
    if rc.returncode != 0:
        logger.error("openssl ts -reply a échoué : %s", rc.stderr.strip()[:200])
        return None

    ts_match = re.search(r"Time stamp:\s*(.+)", rc.stdout)
    serial_match = re.search(r"Serial number:\s*(0x[0-9A-Fa-f]+)", rc.stdout)
    if not ts_match:
        logger.error("Timestamp non trouvé dans la sortie TSR.")
        return None

    # Format OpenSSL : "May 16 15:29:08 2026 GMT".
    try:
        parsed_ts = datetime.strptime(ts_match.group(1).strip(), "%b %d %H:%M:%S %Y %Z")
        parsed_ts = parsed_ts.replace(tzinfo=timezone.utc)
    except ValueError:
        logger.warning("Impossible de parser le timestamp TSA (%s).", ts_match.group(1))
        parsed_ts = datetime.now(timezone.utc)

    # Nettoyage : on peut conserver le TSQ pour traçabilité.
    return {
        "provider": tsa_url,
        "timestamp": parsed_ts,
        "serial": serial_match.group(1) if serial_match else "",
    }


def verify_timestamp(
    pdf_path: Path,
    tsr_path: Path,
    ca_file: Path | str | None = None,
    tsa_cert: Path | str | None = None,
) -> bool:
    """Vérifie un jeton TSR par rapport au PDF et aux certificats TSA.

    Conforme à la procédure ``openssl ts -verify`` : valide la
    signature du TSR par le certificat TSA, la chaîne du certificat
    TSA vers la racine CA, et l'imprint SHA-256 du PDF.
    """
    if shutil.which("openssl") is None:
        logger.warning("openssl absent — vérification TSR sautée.")
        return False

    ca = _resolve_path(ca_file or os.environ.get("HOMO_CI_TSA_CA"), _DEFAULT_CA_FILE)
    cert = _resolve_path(tsa_cert or os.environ.get("HOMO_CI_TSA_CERT"), _DEFAULT_TSA_CERT)

    if not ca.exists() or not cert.exists():
        logger.warning(
            "Certificats TSA absents (CA=%s, cert=%s) — vérification impossible.",
            ca, cert,
        )
        return False

    rc = subprocess.run(
        [
            "openssl", "ts", "-verify",
            "-in", str(tsr_path),
            "-data", str(pdf_path),
            "-CAfile", str(ca),
            "-untrusted", str(cert),
        ],
        capture_output=True, text=True, timeout=20, check=False,
    )
    ok = (rc.returncode == 0) and ("Verification: OK" in (rc.stdout + rc.stderr))
    if not ok:
        logger.error(
            "Vérification TSR échouée pour %s : %s",
            tsr_path, (rc.stdout + rc.stderr).strip()[:300],
        )
    return ok


# ─── Création / vérification de snapshot ─────────────────────────────


def create_snapshot(
    store: EvidenceStore,
    pdf_path: Path,
    version: str,
    change_summary: ChangeSummary,
    tsa_url: str | None = None,
    skip_tsa: bool = False,
    skip_cms: bool = False,
) -> DossierSnapshot:
    """Crée un :class:`DossierSnapshot` scellé par triple mécanisme :

    1. Intégrité chaînée SHA-256 (relatif, local).
    2. Signature CMS détachée par certificat émis par la CA HOMO-CI
       (preuve d'identité de l'émetteur).
    3. Horodatage RFC 3161 par TSA externe (preuve d'antériorité
       opposable à un tiers).

    Args:
        store: EvidenceStore pour résoudre le snapshot parent.
        pdf_path: Chemin du PDF généré.
        version: Numéro de version (ex. ``V1.42``).
        change_summary: Résumé des changements vs parent.
        tsa_url: URL de la TSA (sinon ``HOMO_CI_TSA_URL`` ou FreeTSA).
        skip_tsa: Si vrai, n'effectue pas l'horodatage TSA.
        skip_cms: Si vrai, n'effectue pas la signature CMS.
    """
    pdf_hash = hash_file(pdf_path)
    parent = store.latest_snapshot()
    parent_hash = parent.pdf_hash if parent else None
    parent_id = parent.id if parent else None

    # ── Signature CMS via CA HOMO-CI ────────────────────────────────
    cms_path: Path | None = None
    cms_info: dict[str, str] = {}
    if not skip_cms and os.environ.get("HOMO_CI_SKIP_CMS", "0") != "1":
        try:
            ca_paths = ca_module.ensure_ca()
            cms_path = pdf_path.with_suffix(".p7s")
            if ca_module.sign_pdf(pdf_path, cms_path, ca_paths):
                cms_info = ca_module.signer_info(ca_paths)
            else:
                cms_path = None
        except Exception as exc:  # noqa: BLE001
            logger.error("Signature CMS impossible : %s", exc)
            cms_path = None

    # ── Horodatage RFC 3161 ─────────────────────────────────────────
    tsa_info: dict | None = None
    tsr_path: Path | None = None
    if not skip_tsa and os.environ.get("HOMO_CI_SKIP_TSA", "0") != "1":
        tsr_path = pdf_path.with_suffix(".tsr")
        tsa_info = request_timestamp(pdf_path, tsr_path, tsa_url=tsa_url)
        if tsa_info is None:
            tsr_path = None

    snap = DossierSnapshot(
        id=uuid4(),
        version=version,
        evidence_count=store.count(),
        pdf_path=str(pdf_path),
        pdf_hash=pdf_hash,
        parent_id=parent_id,
        parent_hash=parent_hash,
        change_summary=change_summary,
        signature=hashlib.sha256(
            f"{pdf_hash}|{parent_hash or ''}|{version}".encode("utf-8")
        ).hexdigest(),
        tsa_provider=(tsa_info or {}).get("provider"),
        tsa_tsr_path=str(tsr_path) if tsr_path and tsr_path.exists() else None,
        tsa_timestamp=(tsa_info or {}).get("timestamp"),
        tsa_serial=(tsa_info or {}).get("serial"),
        cms_signature_path=str(cms_path) if cms_path and cms_path.exists() else None,
        cms_signer_subject=cms_info.get("subject"),
        cms_signer_serial=cms_info.get("serial"),
        cms_signer_fingerprint=cms_info.get("fingerprint_sha256"),
    )
    store.save_snapshot(snap)
    logger.info(
        "Snapshot %s scellé : pdf_hash=%s parent=%s cms=%s tsa=%s",
        version,
        pdf_hash[:12],
        (parent_hash or "<root>")[:12],
        snap.cms_signer_serial or "<sans CMS>",
        snap.tsa_serial or "<sans TSA>",
    )
    return snap


def verify_chain(store: EvidenceStore) -> bool:
    """Vérifie la cohérence de la chaîne de snapshots et, le cas
    échéant, la validité des jetons RFC 3161 attachés.

    Retourne ``True`` si toutes les vérifications passent.
    """
    snapshots = list(reversed(store.list_snapshots()))  # ancien → récent
    if not snapshots:
        return True

    all_ok = True
    for i, snap in enumerate(snapshots):
        # 1. Hash chain
        if i == 0:
            if snap.parent_hash:
                logger.error("Premier snapshot %s a un parent_hash non-nul.", snap.version)
                all_ok = False
                continue
        else:
            prev = snapshots[i - 1]
            if snap.parent_hash != prev.pdf_hash:
                logger.error(
                    "Hash chain rompue : %s.parent_hash=%s ≠ %s.pdf_hash=%s",
                    snap.version,
                    (snap.parent_hash or "")[:12],
                    prev.version,
                    prev.pdf_hash[:12],
                )
                all_ok = False
                continue

        # 2. Signature CMS par CA interne (si attachée)
        if snap.cms_signature_path:
            cms_path = Path(snap.cms_signature_path)
            pdf_path = Path(snap.pdf_path)
            try:
                ca_paths = ca_module.ensure_ca()
            except Exception as exc:  # noqa: BLE001
                logger.error("CA HOMO-CI indisponible pour vérification : %s", exc)
                all_ok = False
                ca_paths = None
            if ca_paths and cms_path.exists() and pdf_path.exists():
                if ca_module.verify_pdf_signature(pdf_path, cms_path, ca_paths.ca_crt):
                    logger.info(
                        "Signature CMS de %s vérifiée (serial=%s, signataire=%s).",
                        snap.version, snap.cms_signer_serial, snap.cms_signer_subject,
                    )
                else:
                    logger.error(
                        "Signature CMS de %s rejetée par openssl cms -verify.",
                        snap.version,
                    )
                    all_ok = False
            elif ca_paths:
                logger.warning(
                    "CMS ou PDF absent pour %s — vérification CMS sautée.",
                    snap.version,
                )

        # 3. Horodatage RFC 3161 (si attaché)
        if snap.tsa_tsr_path:
            tsr_path = Path(snap.tsa_tsr_path)
            pdf_path = Path(snap.pdf_path)
            if not tsr_path.exists() or not pdf_path.exists():
                logger.warning(
                    "TSR ou PDF absent pour %s — vérification TSA sautée.",
                    snap.version,
                )
                continue
            if verify_timestamp(pdf_path, tsr_path):
                logger.info(
                    "TSR de %s vérifié (serial=%s, ts=%s).",
                    snap.version, snap.tsa_serial, snap.tsa_timestamp,
                )
            else:
                logger.error("TSR de %s rejeté par openssl ts -verify.", snap.version)
                all_ok = False

    return all_ok
