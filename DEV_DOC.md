# AssociatesManager Plugin - Developer Documentation (English)

## Overview
This document explains how to set up the AssociatesManager plugin and the RNE (Répertoire National des Établissements) integration for GLPI. It covers configuration requirements, file locations, and provides an example configuration file.

## Prerequisites
- GLPI installed and running
- AssociatesManager plugin files placed in the `plugins/associatesmanager/` directory
- Access to the RNE data source

## Plugin Installation
1. Copy the `associatesmanager` plugin folder into your GLPI `plugins/` directory.
2. Go to the GLPI web interface, navigate to **Setup > Plugins**, and activate the AssociatesManager plugin.

## RNE Integration Setup
The plugin requires configuration files for RNE integration. These files contain connection details and parameters for accessing the RNE data.

### Configuration File Location
- Store your RNE configuration files in the `plugins/associatesmanager/config/` directory.
- Example path: `plugins/associatesmanager/config/rne_config.php`

### Example Configuration File (`rne_config.php`)
```php
<?php
return [
    'rne_api_url' => 'https://api.rne.example.com',
    'api_key' => 'YOUR_API_KEY_HERE',
    'timeout' => 30,
    // Add other parameters as needed
];
```

### Notes
- Ensure the configuration file is readable by the web server user.
- Do not commit sensitive information (like API keys) to version control.

## Usage
Once configured, the plugin will use the RNE settings to synchronize or fetch establishment data as required by your workflows.

---

# AssociatesManager - Documentation Développeur (Français)

## Vue d'ensemble
Ce document explique comment mettre en place le plugin AssociatesManager et l'intégration du RNE (Répertoire National des Établissements) pour GLPI. Il détaille les besoins en configuration, l'emplacement des fichiers, et fournit un exemple de fichier de configuration.

## Prérequis
- GLPI installé et fonctionnel
- Dossier du plugin AssociatesManager placé dans `plugins/associatesmanager/`
- Accès à la source de données RNE

## Installation du plugin
1. Copiez le dossier `associatesmanager` dans le répertoire `plugins/` de GLPI.
2. Dans l'interface web de GLPI, allez dans **Configuration > Plugins** et activez le plugin AssociatesManager.

## Mise en place du RNE
Le plugin nécessite des fichiers de configuration pour l'intégration RNE. Ces fichiers contiennent les informations de connexion et les paramètres d'accès aux données RNE.

### Emplacement du fichier de configuration
- Stockez vos fichiers de configuration RNE dans le dossier `plugins/associatesmanager/config/`.
- Exemple de chemin : `plugins/associatesmanager/config/rne_config.php`

### Exemple de fichier de configuration (`rne_config.php`)
```php
<?php
return [
    'rne_api_url' => 'https://api.rne.exemple.fr',
    'api_key' => 'VOTRE_CLE_API_ICI',
    'timeout' => 30,
    // Ajoutez d'autres paramètres si besoin
];
```

### Remarques
- Vérifiez que le fichier de configuration est lisible par l'utilisateur du serveur web.
- Ne versionnez jamais d'informations sensibles (comme les clés API).

## Utilisation
Une fois configuré, le plugin utilisera les paramètres RNE pour synchroniser ou récupérer les données d'établissement selon vos besoins.
