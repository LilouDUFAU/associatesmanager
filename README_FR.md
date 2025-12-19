# GestionAssociés – Plugin GLPI

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
3. Installez puis activez le plugin
4. Retrouvez-le dans le menu **Administration**

### Gestion des associés
#### 1. Vue d'ensemble des associés
- Liste des associés avec recherche par nom ou fournisseur
- Affichage des informations principales : nom, fournisseur, nombre de parts

### Base de données
Le plugin crée 2 tables principales :
- `glpi_plugin_associatesmanager_associates` : Informations sur les associés
- `glpi_plugin_associatesmanager_parts` : Définition des types de parts et historique des attributions (les enregistrements historiques sont conservés dans cette table via le champ `date_fin`)

#### 2. Types d'associés possibles

| Type | Description |
|------|-------------|
| **Personne physique** | Associé lié à un contact GLPI |
| **Autre** | Associé non lié à un contact GLPI (ex. entreprise) |

## 🏗️ Architecture

### Structure des fichiers
```
associatesmanager/
├── AUTHORS.txt
├── CHANGELOG.md              → changement par version
├── hook.php
├── INSTALL.md                → guide installation
├── README.md                 → ce que vous êtes en train de lire
├── setup.php
├── USER_GUIDE.md             → guide utilisateur
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

## 🧠 Concepts clés
- **Modularité** : chaque entité est gérée via une classe dédiée
- **Historisation** : chaque modification de parts est enregistrée
- **Interopérabilité GLPI** : lien avec les contacts GLPI pour les personnes physiques et avec les fournisseurs

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
