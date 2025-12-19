---
# Associates Manager – GLPI Plugin (EN)

[![GLPI Version](https://img.shields.io/badge/GLPI-v10.0.19+-blue.svg)](https://glpi-project.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-green.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2+-red.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Status](https://img.shields.io/badge/Status-Stable-brightgreen.svg)]()

The **Associates Manager Plugin** is an advanced plugin for GLPI (v10.0+ recommended) that enables full management of associates linked to suppliers, tracking of shares, change history, and native integration into the **Administration** menu.

### ✨ Main Features
- 👤 Manage associates (individuals or companies) linked to a supplier
- 💼 Manage shares and share history
- 🔗 Automatic link with GLPI contacts for individuals
- 📝 Full CRUD: **Add**, **Edit**, **Delete** associates, shares, history
- ✅ Visual confirmation after each action
- 🔒 Fine-grained rights by GLPI profile (read, create, update, delete, purge)
- 🌍 Multilingual support (French)

## 🛠️ CRUD Usage Examples

- ➕ **Add** an associate: "New" button → form → validate
- ✏️ **Edit** an associate: "Edit" button on the record → form → validate
- 🗑️ **Delete** an associate: "Delete" button → confirmation
- 🔄 **History**: every share modification is tracked

## 🔒 Rights Management

- **READ**: View data
- **CREATE**: Add
- **UPDATE**: Edit
- **DELETE**: Delete
- **PURGE**: Permanent deletion

## 📦 Installation

### Requirements
- GLPI 10.0+ recommended
- PHP 7.4+ (or 8.1+ depending on GLPI version)
- MySQL 5.7+ or MariaDB

### Method 1: Install from GitHub

```bash
cd /var/www/glpi/plugins
git clone https://github.com/LilouDUFAU/associatesmanager.git
chown -R www-data:www-data associatesmanager
chmod -R 755 associatesmanager
```

### Method 2: Manual installation

1. Download the latest release
2. Extract the archive to `/var/www/glpi/plugins/associatesmanager/`

### Activation

1. Log in to GLPI as a super-admin
2. Go to **Configuration → Plugins**
3. Install and activate the plugin
4. Find it in the **Administration** menu

### Associates Management
#### 1. Associates Overview
- List of associates with search by name or supplier
- Display of main information: name, supplier, number of shares

### Database
The plugin creates 2 main tables:
- `glpi_plugin_associatesmanager_associates`: Associates information
- `glpi_plugin_associatesmanager_parts`: Definition of share types and assignment history (historical records are kept in this table via the `date_fin` field)

#### 2. Possible associate types

| Type | Description |
|------|-------------|
| **Individual** | Associate linked to a GLPI contact |
| **Other** | Associate not linked to a GLPI contact (e.g. company) |

## 🏗️ Architecture

### File structure
```
associatesmanager/
├── AUTHORS.txt
├── CHANGELOG.md              → version changes
├── hook.php
├── INSTALL.md                → installation guide
├── README.md                 → this file
├── setup.php
├── USER_GUIDE.md             → user guide
├── front/
│   ├── associate.form.php
│   ├── associate.php
│   ├── config.form.php
│   ├── part.form.php
│   └── part.php
├── inc/
│   ├── associate.class.php
│   ├── config.class.php
│   ├── menu.class.php
│   └── part.class.php
├── locale/
│   └── fr_FR.po
```

## 🧠 Key Concepts
- **Modularity**: each entity is managed via a dedicated class
- **History**: every share modification is recorded
- **GLPI interoperability**: link with GLPI contacts for individuals and with suppliers

## 📚 Developer Documentation
### Available hooks
```php
// to be completed
```

### Example: creating an associate
```php
// to be completed
```

## 📈 Roadmap
### Upcoming
- 🔄 Automatic synchronization of GLPI contacts
- 📁 CSV export of share history
- 🔔 Notifications on share changes
- 🧩 Compatibility with other GLPI plugins

In case of problems:
- Check GLPI logs: `files/_log/`
- Check permissions on the plugin folder and GLPI cache
- Clear GLPI cache if needed
- See the official GLPI documentation
- Make sure the plugin is enabled

**Rights issues**
- Test with an account with all rights

### Logs

Errors are logged in GLPI logs:
```
files/_log/php-errors.log
files/_log/sql-errors.log
```

## 🤝 Contributing

Contributions are welcome! To contribute:

1. **Fork** the project
2. **Create** a branch for your feature (`git checkout -b feature/new-feature`)
3. **Commit** your changes (`git commit -am 'Add new feature'`)
4. **Push** to the branch (`git push origin feature/new-feature`)
5. **Open** a Pull Request

### Code standards
- Follow GLPI coding conventions
- Document new functions
- Test changes before submitting
- Include FR translations

## 📝 Changelog
See [CHANGELOG.md](./CHANGELOG.md)

## 🐛 Report a bug

If you encounter a problem:

1. Check if the issue is already reported in [Issues](../../issues)
2. Create a new issue including:
   - GLPI version
   - Plugin version
   - Detailed description of the problem
   - Steps to reproduce
   - Error logs if available

## 📄 License

This project is licensed under **GPL v2+** – see [LICENSE](LICENSE) for details.

## 👨‍💻 Author

**Lilou DUFAU** – [Lilou DUFAU](https://github.com/LilouDUFAU)

## 🙏 Acknowledgements

- GLPI team for the framework
- GLPI community for feedback and suggestions
- Project contributors

---

⭐ **Don’t hesitate to star this plugin if you found it useful!**

# GestionAssociés – Plugin GLPI

---

**FR | EN**

Ce document est disponible en français 🇫🇷 et en anglais 🇬🇧.

---

## ✨ Main Features (EN)

- 👤 Manage associates (individuals or companies) linked to a supplier
- 💼 Manage shares and share history
- 🔗 Automatic link with GLPI contacts for individuals
- 📝 Full CRUD: **Add**, **Edit**, **Delete** associates, shares, history
- ✅ Visual confirmation after each action
- 🔒 Fine-grained rights by GLPI profile (read, create, update, delete, purge)
- 🌍 Multilingual support (French)

---

[![GLPI Version](https://img.shields.io/badge/GLPI-v10.0.19+-blue.svg)](https://glpi-project.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-green.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2+-red.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Status](https://img.shields.io/badge/Status-Stable-brightgreen.svg)]()


Le **Plugin Associates Manager** est un plugin avancé pour GLPI (v10.0+ recommandé) permettant la gestion complète des associés liés aux fournisseurs, le suivi des parts sociales, l'historique des modifications, et l'intégration native dans le menu **Administration**.



### ✨ Fonctionnalités principales
- 👤 Gestion des associés (personnes ou sociétés) liés à un fournisseur
- 💼 Gestion des parts sociales et historique d'attribution
- 🔗 Liaison automatique avec les contacts GLPI pour les personnes physiques
- 📝 CRUD complet : **Ajouter**, **Modifier**, **Supprimer** associés, parts, historiques
- ✅ Redirections et confirmations visuelles après chaque action
- 🔒 Droits fins par profils GLPI (lecture, création, modification, suppression, purge)
- 🌍 Support multilingue (français)

## 🛠️ Exemples d’utilisation CRUD

- ➕ **Ajouter** un associé : bouton "Nouveau" → formulaire → valider
- ✏️ **Modifier** un associé : bouton "Modifier" sur la fiche → formulaire → valider
- 🗑️ **Supprimer** un associé : bouton "Supprimer" → confirmation
- 🔄 **Historique** : chaque modification de parts est tracée

## 🔒 Gestion des droits

- **READ** : Voir les données
- **CREATE** : Ajouter
- **UPDATE** : Modifier
- **DELETE** : Supprimer
- **PURGE** : Suppression définitive


## 📦 Installation

### Prérequis
- GLPI 10.0+ recommandé
- PHP 7.4+ (ou 8.1+ selon version GLPI)
- MySQL 5.7+ ou MariaDB

### Méthode 1 : Installation depuis GitHub

```bash
cd /var/www/glpi/plugins
git clone https://github.com/LilouDUFAU/associatesmanager.git
chown -R www-data:www-data associatesmanager
chmod -R 755 associatesmanager
```

### Méthode 2 : Installation manuelle

1. Téléchargez la dernière release
2. Extrayez l'archive dans `/var/www/glpi/plugins/associatesmanager/`

### Activation

1. Connectez-vous à GLPI avec un compte super-administrateur
2. Allez dans **Configuration → Plugins**
3. Installer le plugin puis l'activer
4. Vous trouverez le plugin dans le menu `Administration`

### Gestion des associés
#### 1. Vue d'ensemble des associés
- Liste des associés avec recherche par nom ou fournisseur
- Affichage des informations principales : nom, fournisseur, nombre de parts

### Base de données
Le plugin crée 2 tables principales :
- `glpi_plugin_associatesmanager_associates` : Informations sur les associés
- `glpi_plugin_associatesmanager_parts` : Définition des types de parts et historique des attributions (les enregistrements historiques sont conservés dans cette table via le champ `date_fin`)

#### 2. Types d'associés possibles

| Droit | Description |
|-------|-------------|
| **Personne physique** | Associé lié à un contact GLPI |
| **Autre** | Associé non lié à un contact GLPI (ex. entreprise) |

## 🏗️ Architecture

### Structure des fichiers
```
📁 associatesmanager/
├── 📄 AUTHORS.txt
├── 📄 CHANGELOG.md              → changement par version
├── 📄 hook.php
├── 📄 INSTALL.md                → guide installation
├── 📄 README.md                 → ce que vous êtes en train de lire
├── 📄 setup.php
├── 📄 USER_GUIDE.md             → guide utilisateur 
├── 📁 front/
│   ├── 📄 associate.form.php
│   ├── 📄 associate.php
│   ├── 📄 config.form.php
│   ├── 📄 part.form.php
│   └── 📄 part.php
├── 📁 inc/
│   ├── 📄 associate.class.php
│   ├── 📄 config.class.php
│   ├── 📄 menu.class.php
│   ├── 📄 part.class.php
│   └── 📄 part.class.php
└── 📁 locale/
   └── 📄 fr_FR.po
```



## 🧠 Concepts clés
- **Modularité** : chaque entité est gérée via une classe dédiée
- **Historisation** : chaque modification de parts est enregistrée
- **Interopérabilité GLPI** : lien avec les contacts GLPI pour les personne physiques et avec les fournisseurs (pour lier fournisseur et associés)

## 📚 Documentation développeur
### Hooks disponibles
```php
// faire
```

### Exemple de création d’un associé
```php
// faire
```

## 📈 Roadmap
### À venir
- 🔄 Synchronisation automatique des contacts GLPI
- 📁 Export CSV des historiques de parts
- 🔔 Notifications sur modification de parts
- 🧩 Compatibilité avec d’autres plugins GLPI

En cas de problème :
- Vérifiez les logs GLPI : `files/_log/`
- Vérifiez les permissions sur le dossier du plugin et le cache GLPI
- Videz le cache GLPI si besoin
- Consultez la documentation GLPI officielle
- Confirmez que le plugin est activé

**Problèmes de droits**
- Testez avec un compte ayant tous les droits

### Logs

Les erreurs sont enregistrées dans les logs GLPI :
```
files/_log/php-errors.log
files/_log/sql-errors.log
```

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. **Fork** le projet
2. **Créez** une branche pour votre fonctionnalité (`git checkout -b feature/nouvelle-fonctionnalite`)
3. **Committez** vos changements (`git commit -am 'Ajouter nouvelle fonctionnalité'`)
4. **Push** vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. **Ouvrez** une Pull Request

### Standards de code
- Respecter les conventions de codage GLPI
- Documenter les nouvelles fonctions
- Tester les modifications avant soumission
- Inclure les traductions FR

## 📝 Changelog
Consulter le fichier [CHANGELOG.md](./CHANGELOG.md)

## 🐛 Signaler un bug

Si vous rencontrez un problème :

1. Vérifiez que le problème n'est pas déjà signalé dans les [Issues](../../issues)
2. Créez une nouvelle issue en incluant :
   - Version de GLPI
   - Version du plugin
   - Description détaillée du problème
   - Étapes pour reproduire
   - Logs d'erreur si disponibles

## 📄 Licence

Ce projet est sous licence **GPL v2+** - voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 👨‍💻 Auteur

**Lilou DUFAU** - [Lilou DUFAU](https://github.com/LilouDUFAU)

## 🙏 Remerciements

- Équipe GLPI pour le framework
- Communauté GLPI pour les retours et suggestions
- Contributeurs du projet

---

⭐ **N'hésitez pas à mettre une étoile si ce plugin vous a été utile !**
