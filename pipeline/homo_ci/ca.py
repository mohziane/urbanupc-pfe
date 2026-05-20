"""Autorité de certification interne HOMO-CI.

Le pipeline gère une **CA interne self-signed** dédiée au scellement
des dossiers d'homologation. La hiérarchie est minimale :

* **Racine** — ``HOMO-CI Root CA`` (autosignée, RSA 4096, SHA-256,
  validité 5 ans). Stockée dans ``.homo-ci/ca/ca.key`` + ``ca.crt``.
* **Feuille** — certificat de signature du *Risk Manager* du SI
  (RSA 4096, validité 1 an, ``extendedKeyUsage = emailProtection +
  oid 1.3.6.1.5.5.7.3.36`` pour la signature de documents). Le sujet
  identifie nominativement la personne porteuse de la démarche.

Le PDF est signé en **CMS détaché** (PKCS#7 v3, RFC 5652) via
``openssl cms -sign``. Le jeton signature est stocké à côté du PDF
avec l'extension ``.p7s``. La vérification se fait par
``openssl cms -verify`` avec la CA racine comme ancre de confiance.

.. note::

   La CA HOMO-CI est **self-signed et interne**. Elle ne bénéficie
   d'aucune reconnaissance d'un *Trust Service Provider* (TSP)
   qualifié au sens du règlement eIDAS. Sa valeur est conventionnelle :
   l'AQSSI homologue le SI sous condition d'accepter que cette CA
   est l'autorité émettrice reconnue pour le scellement des dossiers
   relatifs à ce SI. La même implémentation accepterait un certificat
   issu d'une AC publique qualifiée (ex. Certigna, ChamberSign) sans
   modification : seuls les fichiers ``ca.crt`` / ``signer.crt`` et
   ``signer.key`` changeraient. L'identité du porteur (CN, OU, mail)
   reste l'élément probant central.
"""

from __future__ import annotations

import logging
import os
import shutil
import subprocess
from pathlib import Path

logger = logging.getLogger(__name__)


# Chemins par défaut (relatifs au cwd du pipeline).
_DEFAULT_CA_DIR = Path(".homo-ci/ca")

# Sujets X.509 par défaut. Le CN du signataire identifie la personne
# physique porteuse de la démarche ; OU = service rattaché ;
# emailAddress = canal de joignabilité.
_DEFAULT_CA_SUBJECT = (
    "/C=FR/O=HOMO-CI PFE/OU=Risk Management/CN=HOMO-CI Root CA"
)
_DEFAULT_SIGNER_SUBJECT = (
    "/C=FR/O=HOMO-CI PFE/OU=Risk Management"
    "/CN=Mohamed Ziane - Risk Manager PFE - UrbanUpC"
    "/emailAddress=mohziane02@gmail.com"
)

# Validité (en jours).
_CA_VALIDITY_DAYS = 1825   # 5 ans
_SIGNER_VALIDITY_DAYS = 365  # 1 an


def _run(cmd: list[str], **kwargs) -> subprocess.CompletedProcess:
    """Exécute openssl avec timeout et collecte stdout/stderr."""
    return subprocess.run(
        cmd, capture_output=True, text=True, timeout=60, check=False, **kwargs
    )


def _resolve_dir(ca_dir: Path | str | None) -> Path:
    """Résout le répertoire CA, relatif au cwd si non absolu."""
    pp = Path(ca_dir or os.environ.get("HOMO_CI_CA_DIR", _DEFAULT_CA_DIR))
    return pp if pp.is_absolute() else (Path.cwd() / pp)


class CaPaths:
    """Conteneur des chemins de la PKI HOMO-CI."""

    def __init__(self, ca_dir: Path) -> None:
        self.ca_dir = ca_dir
        self.ca_key = ca_dir / "ca.key"
        self.ca_crt = ca_dir / "ca.crt"
        self.signer_key = ca_dir / "signer.key"
        self.signer_csr = ca_dir / "signer.csr"
        self.signer_crt = ca_dir / "signer.crt"
        self.serial = ca_dir / "ca.srl"


def ensure_ca(ca_dir: Path | str | None = None) -> CaPaths:
    """Génère la CA racine et le certificat signataire s'ils n'existent pas.

    Retourne les chemins de la PKI. Idempotent : ne régénère rien si
    les artefacts sont déjà présents et valides.
    """
    if shutil.which("openssl") is None:
        raise RuntimeError("openssl absent — signature CMS impossible.")

    paths = CaPaths(_resolve_dir(ca_dir))
    paths.ca_dir.mkdir(parents=True, exist_ok=True)

    ca_subject = os.environ.get("HOMO_CI_CA_SUBJECT", _DEFAULT_CA_SUBJECT)
    signer_subject = os.environ.get("HOMO_CI_SIGNER_SUBJECT", _DEFAULT_SIGNER_SUBJECT)

    # ── CA racine ───────────────────────────────────────────────────
    if not paths.ca_key.exists():
        logger.info("Génération de la clé privée de la CA racine HOMO-CI.")
        proc = _run(["openssl", "genrsa", "-out", str(paths.ca_key), "4096"])
        if proc.returncode != 0:
            raise RuntimeError(f"genrsa CA a échoué : {proc.stderr.strip()[:200]}")
        paths.ca_key.chmod(0o600)

    if not paths.ca_crt.exists():
        logger.info("Auto-signature du certificat racine HOMO-CI.")
        proc = _run([
            "openssl", "req", "-x509", "-new", "-nodes",
            "-key", str(paths.ca_key),
            "-sha256",
            "-days", str(_CA_VALIDITY_DAYS),
            "-out", str(paths.ca_crt),
            "-subj", ca_subject,
        ])
        if proc.returncode != 0:
            raise RuntimeError(f"req CA a échoué : {proc.stderr.strip()[:200]}")

    # ── Certificat de signature (feuille) ──────────────────────────
    if not paths.signer_key.exists():
        logger.info("Génération de la clé privée du signataire.")
        proc = _run(["openssl", "genrsa", "-out", str(paths.signer_key), "4096"])
        if proc.returncode != 0:
            raise RuntimeError(f"genrsa signer a échoué : {proc.stderr.strip()[:200]}")
        paths.signer_key.chmod(0o600)

    if not paths.signer_crt.exists():
        logger.info("Émission du certificat signataire par la CA HOMO-CI.")
        proc = _run([
            "openssl", "req", "-new",
            "-key", str(paths.signer_key),
            "-out", str(paths.signer_csr),
            "-subj", signer_subject,
        ])
        if proc.returncode != 0:
            raise RuntimeError(f"req CSR a échoué : {proc.stderr.strip()[:200]}")

        # extendedKeyUsage = emailProtection + OID 1.3.6.1.5.5.7.3.36
        # (qcStatements / document signing) — bonne pratique PAdES.
        ext_file = paths.ca_dir / "signer.ext"
        ext_file.write_text(
            "extendedKeyUsage=emailProtection,1.3.6.1.5.5.7.3.36\n",
            encoding="utf-8",
        )
        proc = _run([
            "openssl", "x509", "-req",
            "-in", str(paths.signer_csr),
            "-CA", str(paths.ca_crt),
            "-CAkey", str(paths.ca_key),
            "-CAcreateserial",
            "-out", str(paths.signer_crt),
            "-days", str(_SIGNER_VALIDITY_DAYS),
            "-sha256",
            "-extfile", str(ext_file),
        ])
        if proc.returncode != 0:
            raise RuntimeError(f"x509 signer a échoué : {proc.stderr.strip()[:200]}")

    return paths


def signer_info(paths: CaPaths) -> dict[str, str]:
    """Extrait CN, serial, fingerprint du certificat signataire."""
    info: dict[str, str] = {}

    # Sujet complet.
    proc = _run(["openssl", "x509", "-in", str(paths.signer_crt), "-noout", "-subject"])
    if proc.returncode == 0:
        info["subject"] = proc.stdout.replace("subject=", "").strip()

    # Numéro de série hexa.
    proc = _run(["openssl", "x509", "-in", str(paths.signer_crt), "-noout", "-serial"])
    if proc.returncode == 0:
        info["serial"] = proc.stdout.replace("serial=", "").strip()

    # Fingerprint SHA-256.
    proc = _run([
        "openssl", "x509", "-in", str(paths.signer_crt),
        "-noout", "-fingerprint", "-sha256",
    ])
    if proc.returncode == 0:
        info["fingerprint_sha256"] = (
            proc.stdout.replace("sha256 Fingerprint=", "").strip()
        )

    # Validité.
    proc = _run(["openssl", "x509", "-in", str(paths.signer_crt), "-noout", "-dates"])
    if proc.returncode == 0:
        for line in proc.stdout.splitlines():
            if line.startswith("notBefore="):
                info["not_before"] = line.replace("notBefore=", "").strip()
            elif line.startswith("notAfter="):
                info["not_after"] = line.replace("notAfter=", "").strip()

    return info


def sign_pdf(pdf_path: Path, p7s_path: Path, paths: CaPaths) -> bool:
    """Signe le PDF en CMS détaché (binaire, DER).

    Le PDF reste intact ; la signature est écrite séparément.
    """
    p7s_path.parent.mkdir(parents=True, exist_ok=True)
    proc = _run([
        "openssl", "cms", "-sign",
        "-in", str(pdf_path),
        "-binary",
        "-signer", str(paths.signer_crt),
        "-inkey", str(paths.signer_key),
        "-certfile", str(paths.ca_crt),
        "-outform", "DER",
        "-out", str(p7s_path),
    ])
    if proc.returncode != 0:
        logger.error("CMS sign a échoué : %s", proc.stderr.strip()[:300])
        return False
    return True


def verify_pdf_signature(
    pdf_path: Path,
    p7s_path: Path,
    ca_crt: Path,
) -> bool:
    """Vérifie une signature CMS détachée par rapport au PDF et à la CA."""
    if not p7s_path.exists() or not pdf_path.exists() or not ca_crt.exists():
        logger.warning(
            "Vérification CMS sautée — fichier manquant (p7s=%s, pdf=%s, ca=%s)",
            p7s_path, pdf_path, ca_crt,
        )
        return False

    proc = _run([
        "openssl", "cms", "-verify",
        "-in", str(p7s_path),
        "-inform", "DER",
        "-content", str(pdf_path),
        "-binary",
        "-CAfile", str(ca_crt),
        "-out", "/dev/null",
    ])
    ok = (proc.returncode == 0) and (
        "Verification successful" in (proc.stdout + proc.stderr)
    )
    if not ok:
        logger.error(
            "Vérification CMS rejetée : %s",
            (proc.stdout + proc.stderr).strip()[:300],
        )
    return ok
