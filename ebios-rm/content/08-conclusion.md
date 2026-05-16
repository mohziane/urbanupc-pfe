<div class="atelier no-number" id="conclusion">

# Conclusion et décision proposée à l'AQSSI

## 6.1 Synthèse de l'analyse

L'analyse EBIOS Risk Manager conduite sur le périmètre UrbanUpC en
mai 2026 permet d'éclairer la décision d'homologation par les
constats suivants :

1. Le périmètre d'étude porte **sept valeurs métier** dont deux sont
   critiques pour la mission de l'établissement (l'intégrité du
   dossier scolaire et la disponibilité du portail public).
2. Le SI s'appuie sur **dix biens supports** dont la disposition
   actuelle révèle une **concentration de risque** sur le domaine
   Active Directory `corpnet.local` et sur les identités/secrets
   associés.
3. **Huit événements redoutés** ont été identifiés, dont deux cotés
   G4 (catastrophiques) : la compromission complète du domaine AD
   et la modification frauduleuse de notes étudiantes.
4. **Six sources de risque** sont retenues comme pertinentes, avec
   une attention particulière aux sources *internes ou semi-internes*
   (étudiant malveillant, ancien personnel) souvent sous-évaluées
   dans les analyses universitaires.
5. **Neuf scénarios opérationnels** ont été modélisés. L'évaluation
   initiale identifie **deux scénarios critiques** (SO-5 Docker
   socket à 16/16 et SO-6 ancien personnel à 12/16) et sept scénarios
   élevés.
6. L'application du PACS (14 actions, 19,2 j-h d'effort total)
   ramène **tous les risques sous le seuil critique** et traite
   sept scénarios à un niveau *modéré* ou *faible*. Deux risques
   résiduels demeurent au niveau *élevé* et sont inscrits au
   registre.

## 6.2 Avis du Risk Manager

L'analyse confirme que :

- la conception du périmètre intègre dès l'origine les principaux
  contrôles attendus à un niveau OWASP ASVS L2 sur les applications
  internes ;
- le dispositif de détection (Wazuh, règles 100300–100352, watcher
  Docker) couvre **l'ensemble des scénarios opérationnels** identifiés ;
- les **deux vulnérabilités pédagogiquement plantées** (Docker socket,
  Apache 2.4.49) sont explicitement reconnues, documentées et
  associées à des actions de remédiation chiffrées.

Le Risk Manager **émet un avis favorable** à l'homologation du
périmètre UrbanUpC, **sous réserve** :

::: box box-rec
**1.** De l'application **intégrale et auditée** des actions de
priorité 1 du PACS dans un délai maximal de **trois jours** suivant
la décision d'homologation (R2, R5.2, R5.3, R5.4).

**2.** De l'application des actions de priorité 2 du PACS dans un
délai maximal de **quatorze jours** (R1, R3, R5.1, R5.5, R6, R9).

**3.** De la fixation par l'AQSSI d'une **revue intermédiaire à
trois mois**, conditionnant la poursuite de l'homologation au-delà.

**4.** De l'**inscription formelle au registre des risques
résiduels** :
   - du risque SO-4 (CVE-2021-41773) avec mention explicite du
     caractère pédagogique du choix, bornée à 12 mois ;
   - du risque SO-6 (ancien personnel) avec engagement de revue
     trimestrielle des comptes et des accès.
:::

## 6.3 Recommandations annexes

Au-delà du PACS courant, deux recommandations sont formulées pour
la prochaine itération de l'analyse :

1. **Conduite d'un exercice d'équipe rouge** par un prestataire
   tiers, pour évaluer de manière contradictoire la couverture
   réelle de la détection. Cette évaluation produira une mesure
   plus robuste de la vraisemblance des scénarios SO-2 et SO-7
   (chaînes longues d'attaque AD).

2. **Mise en place du pipeline d'**homologation continue** (action
   R10) selon les modalités décrites au chapitre 13 du mémoire
   principal. Ce pipeline permettra à l'AQSSI de disposer en
   permanence d'une vue actualisée du niveau de risque, sans
   dépendre de la *fraîcheur* annuelle de l'analyse EBIOS RM.

## 6.4 Décision sollicitée

Il est demandé à l'AQSSI UrbanUpC de **prononcer la décision
d'homologation** du périmètre étudié, **pour une durée maximale de
neuf mois**, sous les conditions énoncées en section 6.2. Le présent
document constitue la pièce de référence à joindre au dossier
d'homologation, en complément :

- du tableau des biens supports et des contrôles ASVS L2 vérifiés ;
- du rapport de validation des règles de détection (chapitre 10 du
  mémoire principal) ;
- du procès-verbal de revue interne préalable.

::: signatures

<div class="sig-row">
<div class="sig-block">
<strong>Risk Manager</strong><br>
<small>Direction Cybersécurité UrbanUpC</small><br><br>
Signature et date :
</div>
<div class="sig-block">
<strong>RSSI</strong><br>
<small>Validation technique</small><br><br>
Signature et date :
</div>
</div>

<div class="sig-row">
<div class="sig-block">
<strong>AQSSI</strong><br>
<small>Décision d'homologation</small><br><br>
Signature et date :
</div>
<div class="sig-block">
<strong>DPO</strong><br>
<small>Visa sur les valeurs métier liées aux données personnelles</small><br><br>
Signature et date :
</div>
</div>

:::

</div>
