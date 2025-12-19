# Associates Manager – Guide d'utilisation 🚀

## 👀 Vue d'ensemble

Le plugin **Associates Manager** permet de gérer facilement les associés et leurs parts dans GLPI, avec toutes les opérations d'ajout, modification, suppression et historique.

## ✨ Fonctionnalités principales

- 👤 Gestion des associés (personnes ou sociétés) liés à un fournisseur
- 💼 Gestion des parts sociales et historique d'attribution
- 🔗 Liaison automatique avec les contacts GLPI pour les personnes physiques
- 📝 CRUD complet : **Ajouter**, **Modifier**, **Supprimer** associés, parts, historiques
- ✅ Redirections et confirmations visuelles après chaque action
- 🔒 Droits fins par profils GLPI (lecture, création, modification, suppression, purge)

## 🌐 Intégration RNE (Registre National des Entreprises)

Le plugin Associates Manager peut se connecter à l’API RNE (INPI) pour synchroniser automatiquement les bénéficiaires effectifs d’un fournisseur à partir de son SIREN.

### 🔗 Fonctionnement avec RNE

- Un bouton **Synchroniser avec RNE** apparaît sur la fiche fournisseur (si le SIREN est renseigné).
- Lors de la synchronisation, le plugin interroge l’API RNE et propose d’ajouter ou mettre à jour les associés selon les bénéficiaires effectifs déclarés.
- Les identifiants API RNE sont à configurer dans **Administration → Associates Manager → Configuration**.
- Un historique de synchronisation et les éventuelles erreurs sont affichés à l’utilisateur.

### 🚫 Fonctionnement sans RNE

- Si aucun identifiant API RNE n’est configuré, ou si le SIREN n’est pas renseigné, la synchronisation automatique n’est pas disponible.
- Toutes les opérations CRUD (ajout, modification, suppression) restent possibles manuellement.
- Le plugin fonctionne alors en mode manuel, sans récupération automatique des bénéficiaires effectifs.

> ℹ️ L’intégration RNE est optionnelle : le plugin reste pleinement fonctionnel même sans connexion à l’API RNE.

---

## 🛠️ Exemples d’utilisation CRUD

### ➕ Ajouter un associé
1. Cliquez sur **"Nouveau"** (icône ➕) en haut de la page "Associates"
2. Remplissez le formulaire :
   - **Nom** (obligatoire)
   - **Type** : Personne ou Société (obligatoire)
   - **Fournisseur** (obligatoire)
   - **Contact** (optionnel)
   - **Email**, **Téléphone**, **Adresse**
3. Cliquez sur **"Ajouter"**

> ℹ️ Si vous créez un associé de type "Personne" sans contact lié, un contact sera automatiquement créé et associé au fournisseur.

### ✏️ Modifier un associé
1. Cliquez sur le bouton **"Modifier"** (icône ✏️) sur la fiche de l'associé
2. Modifiez les champs souhaités
3. Cliquez sur **"Enregistrer"**

### 🗑️ Supprimer un associé
1. Cliquez sur le bouton **"Supprimer"** (icône 🗑️) sur la fiche de l'associé
2. Confirmez la suppression

### 🔄 Historique des parts
1. Accédez à **Administration → Associates Manager → Parts History**
2. Cliquez sur **"Nouveau"** pour ajouter une attribution de part
3. Remplissez :
   - **Associé** (obligatoire)
   - **Part** (obligatoire)
   - **Nombre de parts** (obligatoire)
   - **Date d'attribution** (optionnel)
   - **Date de fin** (optionnel)
4. Cliquez sur **"Ajouter"**

Pour visualiser l'historique d'un associé :
1. Ouvrez la fiche d'un associé
2. Cliquez sur l'onglet **"Parts History"**
3. Vous verrez tout l'historique des parts attribuées à cet associé

## 🔒 Gestion des droits

Le plugin utilise un système de droits dédié : `plugin_associatesmanager`

| Droit      | Description                        |
|------------|------------------------------------|
| **READ**   | Voir les données                   |
| **CREATE** | Ajouter de nouveaux éléments       |
| **UPDATE** | Modifier des éléments existants    |
| **DELETE** | Supprimer des éléments             |
| **PURGE**  | Suppression définitive             |

> Les boutons "Nouveau" ou "Supprimer" n'apparaissent que si vous avez le droit correspondant.

## 🧭 Navigation

Le plugin ajoute un menu dans **Administration** :

```
Administration
  └── Associates Manager
       ├── Associates
       ├── Parts
       └── Parts History
```

## 🆘 Support

Pour signaler un bug ou demander une fonctionnalité, contactez l'administrateur système ou ouvrez une issue sur le dépôt GitHub.

---

**Version** : 1.0.4  
**Auteur** : Lilou DUFAU  
**Licence** : GPLv3+
