# Pennylane CLI Tools

Outils CLI PHP pour interroger l’API Pennylane  
Projet utilisé pour la gestion comptable du **Poulailler – Coworking Metz**.

---

# 📁 Structure du projet

```
config.php
config.php.modele
_main.php

pennylane_exporter.php
pennylane_list_categories.php

bilan_annuel_complet.php
bilan_exercice_precedent.sh

lib/
 ├── transactions.php
 └── familles.php

README.md
LICENSE
```

---

# 🔐 Configuration

### 1️⃣ Copier le modèle

```bash
cp config.php.modele config.php
```

### 2️⃣ Renseigner vos identifiants API

Dans `config.php` :

```php
define('API_KEY','VOTRE_API_KEY');
define('COMPANY_ID', 123456);
```

---

# 📚 Commandes disponibles

---

# 1️⃣ Lister les catégories

```bash
php pennylane_list_categories.php
```

Affiche toutes les catégories disponibles au format :

```
Famille.Categorie (ID)
```

Exemple :

```
Charges.Frais bancaires (12345)
Charges.Loyer (67890)
Produits.Ventes (54321)
```

---

# 2️⃣ Calculer un total filtré

Script principal :

```bash
php pennylane_exporter.php --categories="Famille.Categorie"
```

---

## ✅ Règles de filtrage

- ✅ Une transaction doit contenir **toutes** les catégories incluses
- ❌ Elle est exclue si elle contient **au moins une** catégorie exclue
- ✅ Filtre par période inclus
- ✅ Année précédente complète par défaut

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
| --- | --- | --- |
| --categories | ✅   | Catégories à inclure (séparées par virgule) |
| --exclude-categories | ❌   | Catégories à exclure |
| --date-from | ❌   | Date début (YYYY-MM-DD) |
| --date-to | ❌   | Date fin (YYYY-MM-DD) |
| --help | ❌   | Affiche l’aide |

---

## ✅ Exemples

### Année précédente automatique

```bash
php pennylane_exporter.php --categories="Charges.Frais d'avocats"
```

### Plusieurs catégories (AND strict)

```bash
php pennylane_exporter.php \
  --categories="Charges.Frais d'avocats,Charges.Honoraires"
```

### Avec exclusion

```bash
php pennylane_exporter.php \
  --categories="Charges.Frais d'avocats" \
  --exclude-categories="Charges.Frais bancaires"
```

### Période personnalisée

```bash
php pennylane_exporter.php \
  --categories="Produits.Ventes" \
  --date-from=2024-01-01 \
  --date-to=2024-12-31
```

---

# 3️⃣ Bilan annuel complet

```bash
php bilan_annuel_complet.php
```

Ou avec période personnalisée :

```bash
php bilan_annuel_complet.php --date-from=2024-01-01 --date-to=2024-12-31
```

Ce script :

- 📥 Récupère toutes les transactions sur la période
- 📊 Calcule les totaux par famille
- 💰 Sépare recettes et dépenses
- 🧮 Calcule le solde net

---

# 4️⃣ Script exercice précédent

```bash
./bilan_exercice_precedent.sh
```

Lance automatiquement plusieurs exports pour les familles principales.

---

# 🧠 Architecture interne

## lib/transactions.php

- Fonction `getPennylaneTransactions()`
- Gestion complète de la pagination API
- Support :
  - filter
  - limit
  - sort
  - cursor automatique

## lib/familles.php

- Fonction `computeFamilyTotal()`
- Calcule total + nombre de transactions pour une famille donnée

---

# 🔁 Pagination API

Tous les appels API utilisent :

- `limit=100`
- Gestion automatique de `next_cursor`
- Boucle jusqu’à épuisement des pages

---

# ⚖️ Licence

MIT License  
© 2026 Le Poulailler – Coworking Metz