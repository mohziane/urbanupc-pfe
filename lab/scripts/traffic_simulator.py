#!/usr/bin/env python3
"""
CorpNet Traffic Simulator — SOC Background Noise Generator
============================================================
Simule des employés Meridian SA naviguant sur l'intranet pour
créer un fond de trafic réaliste dans les logs SIEM.

Usage :
    python3 traffic_simulator.py --url http://localhost --threads 5 --duration 60
    python3 traffic_simulator.py --url http://localhost --threads 10 --duration 300 --verbose

Profils simulés :
  - Employés normaux  : login → dashboard → documents → annonces → logout
  - Employé maladroit : 50% de logins échoués (brute force léger)
  - Scanner IDOR      : accède à des IDs de documents aléatoires
"""

import argparse
import random
import time
import logging
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime

try:
    import requests
    from requests.exceptions import RequestException
except ImportError:
    print("[ERROR] Le module 'requests' est requis : pip install requests")
    sys.exit(1)

# ---------------------------------------------------------------------------
# Profils d'employés (username, password, rôle, comportement)
# ---------------------------------------------------------------------------
EMPLOYEES = [
    {"username": "m.martin",   "password": "password",   "role": "user",    "behavior": "normal"},
    {"username": "p.bernard",  "password": "123456",     "role": "user",    "behavior": "normal"},
    {"username": "a.lefebvre", "password": "lefebvre",   "role": "user",    "behavior": "normal"},
    {"username": "c.simon",    "password": "simon2024",  "role": "user",    "behavior": "normal"},
    {"username": "l.petit",    "password": "password",   "role": "user",    "behavior": "normal"},
    {"username": "e.roux",     "password": "eroux123",   "role": "user",    "behavior": "normal"},
    {"username": "n.garcia",   "password": "garcia",     "role": "user",    "behavior": "normal"},
    {"username": "j.dupont",   "password": "Dupont2024!","role": "manager", "behavior": "power_user"},
    {"username": "s.david",    "password": "David2024",  "role": "manager", "behavior": "power_user"},
    {"username": "r.thomas",   "password": "rthomas",    "role": "user",    "behavior": "clumsy"},   # logins échoués
    {"username": "f.henry",    "password": "henry",      "role": "user",    "behavior": "idor_scan"}, # IDOR scanner
]

# User-agents réalistes
USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
]

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------
logger = logging.getLogger("corpnet_sim")


def make_session(base_url: str) -> requests.Session:
    """Crée une session HTTP avec User-Agent aléatoire."""
    s = requests.Session()
    s.headers.update({"User-Agent": random.choice(USER_AGENTS)})
    s.max_redirects = 5
    # Ne pas lever d'exception sur les codes 4xx/5xx
    return s


def sleep(min_s: float = 0.5, max_s: float = 3.0) -> None:
    """Délai aléatoire pour simuler une navigation humaine."""
    time.sleep(random.uniform(min_s, max_s))


# ---------------------------------------------------------------------------
# Actions élémentaires
# ---------------------------------------------------------------------------

def do_login(session: requests.Session, base: str, username: str, password: str) -> bool:
    """Tente un login. Retourne True si réussi."""
    try:
        r = session.post(
            f"{base}/index.php",
            data={"username": username, "password": password},
            allow_redirects=True,
            timeout=10,
        )
        # Succès si on atterrit sur le dashboard (pas sur index.php)
        return "dashboard" in r.url or r.url.rstrip("/").endswith(base.rstrip("/"))
    except RequestException:
        return False


def do_get(session: requests.Session, url: str, label: str, verbose: bool) -> int:
    """Effectue un GET et log le statut."""
    try:
        r = session.get(url, timeout=10, allow_redirects=True)
        if verbose:
            logger.info("[%s] GET %-50s → %d", label, url, r.status_code)
        return r.status_code
    except RequestException as e:
        if verbose:
            logger.warning("[%s] GET %-50s → ERROR: %s", label, url, e)
        return 0


# ---------------------------------------------------------------------------
# Comportements
# ---------------------------------------------------------------------------

def behavior_normal(session, base, emp, verbose):
    """Navigation standard : dashboard, documents, annonces."""
    pages = [
        f"{base}/dashboard.php",
        f"{base}/documents.php",
        f"{base}/announcements.php",
        f"{base}/services.php",
        f"{base}/search.php?q={random.choice(['martin','rh','finance','projet'])}",
    ]
    for page in random.sample(pages, k=random.randint(2, len(pages))):
        do_get(session, page, emp["username"], verbose)
        sleep(1, 4)

    # Consulte quelques documents
    for doc_id in random.sample(range(1, 15), k=random.randint(1, 3)):
        do_get(session, f"{base}/document-view.php?id={doc_id}", emp["username"], verbose)
        sleep(0.5, 2)


def behavior_power_user(session, base, emp, verbose):
    """Manager : toutes les pages + admin."""
    behavior_normal(session, base, emp, verbose)
    sleep(1, 2)
    for page in [f"{base}/admin.php", f"{base}/admin-services.php"]:
        do_get(session, page, emp["username"], verbose)
        sleep(1, 3)


def behavior_clumsy(session, base, emp, verbose):
    """Employé maladroit : tente un mauvais login avant de réussir."""
    wrong_passwords = ["password123", "azerty", "123456", emp["username"]]
    do_login(session, base, emp["username"], random.choice(wrong_passwords))
    if verbose:
        logger.info("[%s] LOGIN échoué (intentionnel)", emp["username"])
    sleep(2, 5)
    # Re-tente avec le bon mot de passe
    ok = do_login(session, base, emp["username"], emp["password"])
    if ok:
        behavior_normal(session, base, emp, verbose)


def behavior_idor_scan(session, base, emp, verbose):
    """Scan IDOR : accède à des IDs de documents/utilisateurs arbitraires."""
    # Documents
    for doc_id in random.sample(range(1, 30), k=random.randint(5, 10)):
        do_get(session, f"{base}/document-view.php?id={doc_id}", f"{emp['username']}(IDOR)", verbose)
        sleep(0.2, 0.8)
    # API utilisateurs (IDOR)
    for user_id in random.sample(range(1, 16), k=random.randint(3, 6)):
        do_get(session, f"{base}/api/users.php?id={user_id}", f"{emp['username']}(API-IDOR)", verbose)
        sleep(0.1, 0.5)


# ---------------------------------------------------------------------------
# Session complète d'un employé
# ---------------------------------------------------------------------------

def run_employee_session(base: str, emp: dict, verbose: bool, stop_time: float) -> dict:
    """
    Exécute des sessions répétées pour un employé jusqu'à stop_time.
    Retourne des stats sommaires.
    """
    stats = {"sessions": 0, "errors": 0, "username": emp["username"]}

    while time.time() < stop_time:
        session = make_session(base)
        try:
            # Login
            ok = do_login(session, base, emp["username"], emp["password"])
            if not ok and emp["behavior"] != "clumsy":
                stats["errors"] += 1
                sleep(5, 10)
                continue

            stats["sessions"] += 1
            label = emp["username"]
            if verbose:
                logger.info("[%s] SESSION #%d démarrée (behavior=%s)", label, stats["sessions"], emp["behavior"])

            # Comportement selon profil
            if emp["behavior"] == "normal":
                behavior_normal(session, base, emp, verbose)
            elif emp["behavior"] == "power_user":
                behavior_power_user(session, base, emp, verbose)
            elif emp["behavior"] == "clumsy":
                behavior_clumsy(session, base, emp, verbose)
            elif emp["behavior"] == "idor_scan":
                behavior_idor_scan(session, base, emp, verbose)

            # Logout
            do_get(session, f"{base}/logout.php", label, verbose)

        except Exception as e:
            stats["errors"] += 1
            if verbose:
                logger.error("[%s] Exception: %s", emp["username"], e)
        finally:
            session.close()

        # Pause entre sessions (simule fin de journée / reprise)
        sleep(5, 20)

    return stats


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="CorpNet Traffic Simulator — génère du trafic réaliste pour SOC/SIEM"
    )
    parser.add_argument("--url",      default="http://localhost", help="URL de base de CorpNet")
    parser.add_argument("--threads",  type=int, default=5,        help="Nombre d'employés simultanés")
    parser.add_argument("--duration", type=int, default=60,       help="Durée de simulation en secondes")
    parser.add_argument("--verbose",  action="store_true",        help="Affiche chaque requête HTTP")
    args = parser.parse_args()

    # Logging
    level = logging.DEBUG if args.verbose else logging.INFO
    logging.basicConfig(
        level=level,
        format="%(asctime)s %(levelname)-8s %(message)s",
        datefmt="%H:%M:%S",
    )

    base     = args.url.rstrip("/")
    threads  = min(args.threads, len(EMPLOYEES))
    duration = args.duration
    stop_time = time.time() + duration

    logger.info("=" * 60)
    logger.info("CorpNet Traffic Simulator")
    logger.info("  URL      : %s", base)
    logger.info("  Threads  : %d", threads)
    logger.info("  Durée    : %ds", duration)
    logger.info("  Démarrage: %s", datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
    logger.info("=" * 60)

    # Sélectionner les employés (mélangés, limités au nombre de threads)
    pool = random.sample(EMPLOYEES, k=threads)

    with ThreadPoolExecutor(max_workers=threads) as executor:
        futures = {
            executor.submit(run_employee_session, base, emp, args.verbose, stop_time): emp
            for emp in pool
        }
        for future in as_completed(futures):
            emp = futures[future]
            try:
                result = future.result()
                logger.info(
                    "  %-15s → %d sessions, %d erreurs",
                    result["username"], result["sessions"], result["errors"]
                )
            except Exception as e:
                logger.error("  %-15s → Exception: %s", emp["username"], e)

    logger.info("=" * 60)
    logger.info("Simulation terminée.")


if __name__ == "__main__":
    main()
