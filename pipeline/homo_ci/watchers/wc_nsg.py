"""Watcher Azure Network Security Groups.

Collecte la matrice des règles NSG du Resource Group d'étude et
produit une Evidence par règle. La preuve est cartographiée sur :

* **ANSSI étape 4** (cartographie du SI) — la matrice NSG est un
  artefact de cartographie réseau ;
* **ANSSI étape 6** (mesures de sécurité) — chaque règle est une
  mesure technique opérationnelle ;
* **ISO/IEC 27002:2022 §8.20** *Networks security*.

Authentification : ``DefaultAzureCredential`` (variables d'env
ou *Managed Identity*).
"""

from __future__ import annotations

import os
from typing import Any

from ..models import Category, Evidence, Materiality, Scope, SourceType, Weight
from .base import BaseWatcher


class NSGWatcher(BaseWatcher):
    """Surveille les règles NSG d'un Resource Group Azure."""

    source = SourceType.NSG

    # Mapping standard pour les preuves NSG (constant — défini *a priori*).
    SCOPE = Scope(
        anssi_steps=(4, 6),
        iso_controls=("8.20", "8.21"),
        asvs_controls=(),
    )

    def __init__(
        self,
        store: Any,
        subscription_id: str | None = None,
        resource_group: str | None = None,
    ) -> None:
        super().__init__(store)
        self.subscription_id = subscription_id or os.environ.get("AZURE_SUBSCRIPTION_ID")
        self.resource_group = resource_group or os.environ.get(
            "RESOURCE_GROUP", "RG-PFE-SOC"
        )
        if not self.subscription_id:
            raise ValueError("AZURE_SUBSCRIPTION_ID must be set.")

    def collect(self) -> list[Evidence]:
        # Import paresseux pour permettre l'instanciation en environnement
        # de test sans le SDK Azure installé.
        from azure.identity import DefaultAzureCredential
        from azure.mgmt.network import NetworkManagementClient

        cred = DefaultAzureCredential(exclude_interactive_browser_credential=True)
        client = NetworkManagementClient(cred, self.subscription_id)

        evidences: list[Evidence] = []

        for nsg in client.network_security_groups.list(self.resource_group):
            for rule in nsg.security_rules or []:
                payload = self._serialize_rule(nsg.name, rule)
                ref = f"{nsg.name}/{rule.name}"
                materiality = self._classify_materiality(rule)

                evidences.append(
                    Evidence.from_payload(
                        source=self.source,
                        category=Category.NSG,
                        ref=ref,
                        payload=payload,
                        scope=self.SCOPE,
                        weight=Weight.AUTO,
                        materiality=materiality,
                    )
                )

        self.log.info("Collected %d NSG rules from RG %s.", len(evidences), self.resource_group)
        return evidences

    @staticmethod
    def _serialize_rule(nsg_name: str, rule: Any) -> dict[str, Any]:
        """Sérialise une règle NSG dans un format stable et hashable."""

        def _addr(field_value: Any, plural: Any) -> list[str]:
            if plural:
                return sorted(plural)
            return [field_value] if field_value else []

        def _ports(field_value: Any, plural: Any) -> list[str]:
            if plural:
                return sorted(plural)
            return [field_value] if field_value else []

        return {
            "nsg": nsg_name,
            "name": rule.name,
            "priority": int(rule.priority) if rule.priority else 0,
            "direction": str(rule.direction),
            "access": str(rule.access),
            "protocol": str(rule.protocol),
            "source_addresses": _addr(rule.source_address_prefix, rule.source_address_prefixes),
            "source_ports": _ports(rule.source_port_range, rule.source_port_ranges),
            "destination_addresses": _addr(
                rule.destination_address_prefix, rule.destination_address_prefixes
            ),
            "destination_ports": _ports(
                rule.destination_port_range, rule.destination_port_ranges
            ),
        }

    @staticmethod
    def _classify_materiality(rule: Any) -> Materiality:
        """Heuristique de matérialité.

        Une règle Allow venant d'Internet vers une destination sensible
        est *high* ; une règle Deny ou interne est *medium* par défaut.
        """
        access = str(rule.access).lower()
        srcs = (rule.source_address_prefixes or []) + (
            [rule.source_address_prefix] if rule.source_address_prefix else []
        )
        from_internet = any(s and s.lower() in ("internet", "*", "0.0.0.0/0") for s in srcs)
        if access == "allow" and from_internet:
            return Materiality.HIGH
        return Materiality.MEDIUM
