# Pennylane CLI Tools

Outils CLI PHP pour interroger l’API Pennylane.

---

# 📁 Structure

```
config.php
config.php.modele
pennylane_exporter.php
pennylane_list_categories.php
README.md
```

---

# 🔐 Configuration

### 1️⃣ Copier le modèle

```bash
cp config.php.modele config.php
```

### 2️⃣ Renseigner vos identifiants

Dans `config.php` :

```php
define('API_KEY','VOTRE_API_KEY');
define('COMPANY_ID', 123456);
```



---

# 📚 1️⃣ Lister les catégories disponibles

```bash
php pennylane_list_categories.php
```

Affiche :

```
Charges.Frais bancaires (12345)
Charges.Loyer (67890)
Produits.Ventes (54321)
```

Format :  
```
Famille.Categorie (ID)
```

---

# 💰 2️⃣ Calculer un total filtré

Script :

```bash
php pennylane_exporter.php --categories="Famille.Categorie"
```

---

## 🔎 Règles

- ✅ Une transaction doit contenir **toutes** les catégories incluses
- ❌ Elle est exclue si elle contient **au moins une** catégorie exclue
- ✅ Filtre par période

---

## 📅 Dates

Si aucune date n’est fournie :

```
Année civile précédente complète
Ex: 2025-01-01 → 2025-12-31
```

---

## ✅ Paramètres

| Paramètre | Obligatoire | Description |
|-----------|------------|-------------|
| --categories | ✅ | Catégories à inclure |
| --exclude-categories | ❌ | Catégories à exclure |
| --date-from | ❌ | Date début |
| --date-to | ❌ | Date fin |
| --help | ❌ | Affiche l’aide |

---

## ✅ Exemples

### Année dernière automatique

```bash
php pennylane_exporter.php --categories="Charges.Frais d'avocats"
```

---

### Plusieurs catégories (AND strict)

```bash
php pennylane_exporter.php \
  --categories="Charges.Frais d'avocats,Charges.Honoraires"
```

---

### Avec exclusion

```bash
php pennylane_exporter.php \
  --categories="Charges.Frais d'avocats" \
  --exclude-categories="Charges.Frais bancaires"
```

---

### Période personnalisée

```bash
php pennylane_exporter.php \
  --categories="Produits.Ventes" \
  --date-from=2024-01-01 \
  --date-to=2024-12-31
```
