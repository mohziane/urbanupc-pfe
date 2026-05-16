<div class="frontmatter no-number">

# Synthèse exécutive {.no-number}

## Objet de l'étude

Le présent document restitue l'analyse de risque conduite, selon la
méthode **EBIOS Risk Manager** publiée par l'ANSSI, sur le périmètre
du système d'information UrbanUpC dans sa configuration de mai 2026.
Le périmètre couvre l'Espace Numérique de Travail public (portail
CorpNet), les deux applications internes nouvellement déployées
(MaFormation pour les étudiants, MaCandidature pour les candidats),
l'annuaire `corpnet.local`, et le SIEM Wazuh associé. L'étude vise à
**éclairer la décision d'homologation** de ce périmètre par l'AQSSI.

## Méthodologie

L'analyse a été conduite selon les cinq ateliers EBIOS RM :

| Atelier | Objet | Livrable |
|---|---|---|
| 1 | Cadrage et socle de sécurité | Périmètre, VM, BS, ER, socle |
| 2 | Sources de risque et objectifs visés | Couples SR/OV pertinents |
| 3 | Scénarios stratégiques | Chemins d'attaque de niveau écosystème |
| 4 | Scénarios opérationnels | Chaînes techniques d'attaque, vraisemblance |
| 5 | Traitement du risque | PACS, risques résiduels, KRI |

## Principaux résultats

L'analyse identifie **sept valeurs métier** critiques pour
l'établissement, **dix biens supports** dont quatre concentrent
l'essentiel de l'exposition, et **huit événements redoutés** dont
deux sont cotés en gravité catastrophique (G4) : la compromission du
domaine Active Directory et la modification frauduleuse de notes
étudiantes.

Six **sources de risque** ont été retenues à l'issue de l'atelier 2,
en confrontation avec huit **objectifs visés**. Les couples
SR × OV jugés pertinents donnent lieu à **six scénarios stratégiques**
puis **neuf scénarios opérationnels** instanciés au niveau technique.

L'évaluation initiale du niveau de risque, fondée sur le produit
gravité × vraisemblance (G×V) sur une échelle de 1 à 16, fait
ressortir :

- **deux risques critiques** (≥ 12/16) : l'évasion de conteneur via
  *Docker socket* dans MaCandidature, et la compromission du domaine
  AD par Kerberoasting + escalade ;
- **quatre risques élevés** (6 à 9/16) : *credential stuffing* sur
  MaFormation, exploitation de CVE-2021-41773, *phishing* avec vol
  de jetons, modification frauduleuse de notes par ancien personnel ;
- **trois risques modérés à faibles** : indisponibilité du portail,
  divulgation d'informations par moteur de recherche, mauvaise
  configuration NSG.

## Recommandations principales

Le Risk Manager recommande à l'autorité d'homologation :

<div class="box box-rec">
**1.** D'**homologuer le périmètre pour une durée maximale de neuf
mois**, sous réserve de l'application stricte du Plan d'Amélioration
Continue de la Sécurité (PACS) joint, et notamment de ses actions
prioritaires R2 (suppression de la vulnérabilité *Docker socket*),
R5.1 (passage de l'authentification LDAP en LDAPS) et R5.2 à R5.4
(traitement des fragilités AD plantées en environnement de
démonstration).

**2.** D'**imposer une revue de risque intermédiaire à trois mois**
pour évaluer l'efficacité des mesures mises en œuvre.

**3.** D'**inscrire au registre des risques résiduels** les trois
risques que la conception ne peut pas traiter dans le périmètre du
projet courant (notamment l'exposition Internet du portail
*legacy* CorpNet).
</div>

## Cartographie de synthèse

::: heatmap
                Vraisemblance →
                V1     V2     V3     V4
G4 Catastr.     .      SO-2   SO-6   SO-5  ◀ 1 critique
G3 Grave        .      .      SO-1   SO-3  ◀ 2 critiques (V3 plafonné)
G3 Grave        .      SO-7   SO-8   .
G2 Significat.  .      .      SO-9   SO-4  ◀ 1 élevé (cas du legacy)
G1 Mineure      .      .      .      .

                                     ↑ G : Gravité
                                     ↑ V : Vraisemblance
:::

## Statut du document

Ce document est soumis à l'AQSSI UrbanUpC pour avis et décision
d'homologation. Il est révisé annuellement, avec une **revue
intermédiaire à chaque modification significative** du périmètre
(ajout d'application, ajout de classe d'utilisateurs, changement
d'hébergement).

</div>
