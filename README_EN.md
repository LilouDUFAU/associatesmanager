# AssociatesManager – GLPI Plugin

[![GLPI Version](https://img.shields.io/badge/GLPI-v10.0.19+-blue.svg)](https://glpi-project.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-green.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2+-red.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Status](https://img.shields.io/badge/Status-Stable-brightgreen.svg)]()

The **Associates Manager** plugin is an advanced GLPI extension (v10.0+ recommended) for complete management of associates linked to suppliers, share tracking, change history, and native integration into the **Administration** menu.

### ✨ Main Features
- 👤 Manage associates (individuals or companies) linked to a supplier
- 💼 Manage shares and share history
- 🔗 Automatic link with GLPI contacts for individuals
- 📝 Full CRUD: **Add**, **Edit**, **Delete** associates, shares, history
- ✅ Visual confirmations and redirections after each action
- 🔒 Fine-grained rights by GLPI profile (read, create, update, delete, purge)
- 🌍 Multilingual support (English)

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

### Prerequisites
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

### Method 2: Manual Installation

1. Download the latest release
2. Extract the archive into `/var/www/glpi/plugins/associatesmanager/`

### Activation

1. Log in to GLPI as a super-admin
2. Go to **Configuration → Plugins**
3. Install and activate the plugin
4. Find it in the **Administration** menu

### Managing Associates
#### 1. Associates Overview
- List associates with search by name or supplier
- Display main info: name, supplier, number of shares

### Database
The plugin creates 2 main tables:
- `glpi_plugin_associatesmanager_associates`: Associates information
- `glpi_plugin_associatesmanager_parts`: Share types and share history (historical records are kept in this table via the `date_fin` field)

#### 2. Possible Associate Types

| Type | Description |
|------|-------------|
| **Individual** | Associate linked to a GLPI contact |
| **Other** | Associate not linked to a GLPI contact (e.g., company) |

## 🏗️ Architecture

### File Structure
```
associatesmanager/
├── AUTHORS.txt
├── CHANGELOG.md              → version changes
├── hook.php
├── INSTALL.md                → installation guide
├── README.md                 → what you are reading
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
- **GLPI Interoperability**: link with GLPI contacts for individuals and with suppliers

## 🤝 Contributing

Contributions are welcome! To contribute:

1. **Fork** the project
2. **Create** a branch for your feature (`git checkout -b feature/new-feature`)
3. **Commit** your changes (`git commit -am 'Add new feature'`)
4. **Push** to the branch (`git push origin feature/new-feature`)
5. **Open** a Pull Request

### Coding Standards
- Follow GLPI coding conventions
- Document new functions
- Test changes before submitting
- Include EN translations

## 📝 Changelog
See [CHANGELOG.md](./CHANGELOG.md)

## 🐛 Reporting Bugs

If you encounter a problem:

1. Check if the issue is already reported in [Issues](../../issues)
2. Create a new issue including:
	- GLPI version
	- Plugin version
	- Detailed description
	- Steps to reproduce
	- Error logs if available

## 📄 License

This project is licensed under **GPL v2+** - see [LICENSE](LICENSE) for details.

## 👨‍💻 Author

**Lilou DUFAU** - [Lilou DUFAU](https://github.com/LilouDUFAU)

## 🙏 Acknowledgements

- GLPI team for the framework
- GLPI community for feedback and suggestions
- Project contributors

---

⭐ **If you find this plugin useful, please star the project!**
