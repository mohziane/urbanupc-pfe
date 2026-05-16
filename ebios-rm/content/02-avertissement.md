<div class="frontmatter no-number">

# Avertissement méthodologique {.no-number}

## Sur la nature du présent document

Ce document est rédigé dans le cadre du *Projet de Fin d'Études* du
Master 2 Cybersécurité de l'Université Paris Cité. Il prend la
**forme d'une analyse de risque opérationnelle** menée par un Risk
Manager pour une autorité d'homologation. Bien que l'environnement
UrbanUpC soit *fictif*, l'analyse mobilise des composants techniques
réellement déployés (souscription Azure, conteneurs Docker, instance
Wazuh, domaine Active Directory) et applique strictement la méthode
EBIOS RM telle que prescrite par l'ANSSI [ANSSI-EBIOS-2018].

## Sur les échelles de cotation retenues

Conformément au *Guide EBIOS RM*, deux échelles ordinales à quatre
niveaux sont mobilisées :

<dl class="terms">
<dt>Gravité (G1 → G4)</dt>
<dd>Mesure de l'impact d'un événement redouté ou d'un scénario sur
les valeurs métier.</dd>
<dt>Vraisemblance (V1 → V4)</dt>
<dd>Mesure de la plausibilité d'un scénario, en fonction de la
motivation, des ressources et de l'expertise nécessaires à
l'attaquant.</dd>
</dl>

Le **niveau de risque** est calculé par le produit G × V sur l'échelle
1–16, ventilé en quatre paliers :

| Niveau | Code | Interprétation | Action attendue |
|---|---|---|---|
| 1–2  | Faible    | Risque acceptable en l'état | Suivi sans action |
| 3–4  | Modéré    | Risque tolérable sous conditions | Mesures de réduction recommandées |
| 6–9  | Élevé     | Risque non tolérable en l'état | Mesures de traitement obligatoires |
| 12–16 | Critique | Risque inacceptable | Traitement immédiat avant mise en service |

Table : Échelle des niveaux de risque retenue pour la présente analyse.

## Sur les références utilisées

L'analyse mobilise les référentiels suivants :

| Référence | Usage |
|---|---|
| ANSSI — *EBIOS Risk Manager* (2018) | Méthodologie centrale |
| ANSSI — *Guide de l'homologation* (2020) | Articulation avec la décision d'homologation |
| ANSSI — *Recommandations Active Directory* (2017) | Sources des bonnes pratiques AD |
| MITRE — *ATT&CK Framework* v14.1 | Cartographie des techniques adverses |
| OWASP ASVS v4.0.3 | Évaluation du socle applicatif |
| RGPD (UE 2016/679) | Périmètre des données personnelles |
| NIS 2 (UE 2022/2555) | Exigences applicables aux opérateurs |
| CIS Docker Benchmark v1.6 | Évaluation du socle conteneurs |

Table : Référentiels mobilisés.

## Sur les conventions de notation

Les acronymes suivants sont utilisés tout au long de l'étude :

| Acronyme | Définition |
|---|---|
| VM | Valeur Métier |
| BS | Bien Support |
| ER | Événement Redouté |
| SR | Source de Risque |
| OV | Objectif Visé |
| SS | Scénario Stratégique |
| SO | Scénario Opérationnel |
| PACS | Plan d'Amélioration Continue de la Sécurité |
| KRI | Key Risk Indicator (indicateur de risque) |
| AQSSI | Autorité Qualifiée pour la Sécurité des SI |

## Sur la limite de portée juridique

Cette analyse est conduite à des fins **pédagogiques et démonstratives**.
Elle n'engage ni l'Université Paris Cité, ni aucun organisme tiers, et
n'a pas valeur de décision d'homologation au sens juridique du terme.
Elle fournit toutefois le matériau méthodologique complet d'une telle
décision, transposable à un contexte opérationnel réel.

</div>
