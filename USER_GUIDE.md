---

# Associates Manager – User Guide (EN)

<p align="right"><a href="#associates-manager--guide-dutilisation-🚀" style="font-weight:bold;">🇫🇷 Version française</a></p>

## 👀 Overview

The **Associates Manager** plugin allows you to easily manage associates and their shares in GLPI, with all add, edit, delete, and history operations.

## ✨ Main Features

- 👤 Manage associates (individuals or companies) linked to a supplier
- 💼 Manage shares and share history
- 🔗 Automatic link with GLPI contacts for individuals
- 📝 Full CRUD: **Add**, **Edit**, **Delete** associates, shares, history
- ✅ Visual confirmation after each action
- 🔒 Fine-grained rights by GLPI profile (read, create, update, delete, purge)

## 🌐 RNE Integration (French National Business Register)

The Associates Manager plugin can connect to the RNE (INPI) API to automatically synchronize a supplier’s beneficial owners using its SIREN number.

### 🔗 How it works with RNE

- A **Synchronize with RNE** button appears on the supplier page (if SIREN is set).
- When synchronizing, the plugin queries the RNE API and suggests adding or updating associates according to the declared beneficial owners.
- RNE API credentials must be set in **Administration → Associates Manager → Configuration**.
- A synchronization history and any errors are shown to the user.

### 🚫 How it works without RNE

- If no RNE API credentials are set, or if SIREN is missing, automatic synchronization is not available.
- All CRUD operations (add, edit, delete) remain possible manually.
- The plugin then works in manual mode, without automatic retrieval of beneficial owners.

> ℹ️ RNE integration is optional: the plugin remains fully functional even without RNE API connection.

---

## 🛠️ CRUD Usage Examples

### ➕ Add an associate
1. Click **"New"** (➕ icon) at the top of the "Associates" page
2. Fill in the form:
   - **Name** (required)
   - **Type**: Individual or Company (required)
   - **Supplier** (required)
   - **Contact** (optional)
   - **Email**, **Phone**, **Address**
3. Click **"Add"**

> ℹ️ If you create an associate of type "Individual" without a linked contact, a contact will be automatically created and linked to the supplier.

### ✏️ Edit an associate
1. Click the **"Edit"** button (✏️ icon) on the associate’s record
2. Edit the desired fields
3. Click **"Save"**

### 🗑️ Delete an associate
1. Click the **"Delete"** button (🗑️ icon) on the associate’s record
2. Confirm deletion

### 🔄 Shares history
1. Go to **Administration → Associates Manager → Parts History**
2. Click **"New"** to add a share assignment
3. Fill in:
   - **Associate** (required)
   - **Share** (required)
   - **Number of shares** (required)
   - **Assignment date** (optional)
   - **End date** (optional)
4. Click **"Add"**

To view an associate’s history:
1. Open the associate’s record
2. Click the **"Parts History"** tab
3. You will see the full history of shares assigned to that associate

## 🔒 Rights management

The plugin uses a dedicated rights system: `plugin_associatesmanager`

| Right      | Description                        |
|------------|------------------------------------|
| **READ**   | View data                          |
| **CREATE** | Add new items                      |
| **UPDATE** | Edit existing items                |
| **DELETE** | Delete items                       |
| **PURGE**  | Permanent deletion                 |

> The "New" or "Delete" buttons only appear if you have the corresponding right.

## 🧭 Navigation

The plugin adds a menu in **Administration**:

```
Administration
  └── Associates Manager
       ├── Associates
       ├── Parts
       └── Parts History
```

## 🆘 Support

To report a bug or request a feature, contact your system administrator or open an issue on the GitHub repository.

---

**Version**: 1.0.4  
**Author**: Lilou DUFAU  
**License**: GPLv3+


# Associates Manager – Guide d'utilisation 🚀

<p align="right"><a href="#associates-manager--user-guide-en" style="font-weight:bold;">🇬🇧 English version</a></p>

---

**FR | EN**

Ce guide est disponible en français 🇫🇷 et en anglais 🇬🇧.

---


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

## 🌐 RNE Integration (French National Business Register)

The Associates Manager plugin can connect to the RNE (INPI) API to automatically synchronize a supplier’s beneficial owners using its SIREN number.

### 🔗 How it works with RNE

- A **Synchronize with RNE** button appears on the supplier page (if SIREN is set).
- When synchronizing, the plugin queries the RNE API and suggests adding or updating associates according to the declared beneficial owners.
- RNE API credentials must be set in **Administration → Associates Manager → Configuration**.
- A synchronization history and any errors are shown to the user.

### 🚫 How it works without RNE

- If no RNE API credentials are set, or if SIREN is missing, automatic synchronization is not available.
- All CRUD operations (add, edit, delete) remain possible manually.
- The plugin then works in manual mode, without automatic retrieval of beneficial owners.

> ℹ️ RNE integration is optional: the plugin remains fully functional even without RNE API connection.

---

## 🛠️ Exemples d’utilisation CRUD

### ➕ Ajouter un associé
1. Cliquez sur **"Nouveau"** (icône ➕) en haut de la page "Associates"
2. Remplissez le formulaire :
   - **Nom** (obligatoire)

   <div align="center">
     <button onclick="document.getElementById('guide-fr').style.display='block';document.getElementById('guide-en').style.display='none';">🇫🇷 Version française</button>
     <button onclick="document.getElementById('guide-en').style.display='block';document.getElementById('guide-fr').style.display='none';">🇬🇧 English version</button>
   </div>

   <div id="guide-fr" style="display:block;">

   # Associates Manager – Guide d'utilisation 🚀

   ...existing code français...

   </div>

   <div id="guide-en" style="display:none;">

   # Associates Manager – User Guide (EN)

   ...existing code anglais...

   </div>
   - **Associé** (obligatoire)
