# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

PHP CLI tools to query the Pennylane accounting API for **Le Poulailler – Coworking Metz**. No build step, no framework, no tests — pure PHP scripts run directly from the command line.

## Setup

```bash
cp config.php.modele config.php
# Then edit config.php and set API_KEY and COMPANY_ID
```

## Running scripts

```bash
# List available categories (Famille.Categorie format)
php pennylane_list_categories.php

# Filter transactions by category (defaults to previous calendar year)
php pennylane_exporter.php --categories="Famille.Charges"
php pennylane_exporter.php --categories="Famille.Charges" --exclude-categories="Famille.Investissements" --date-from=2024-01-01 --date-to=2024-12-31

# Full annual balance sheet
php bilan_annuel_complet.php
php bilan_annuel_complet.php --date-from=2024-01-01 --date-to=2024-12-31

# Run previous fiscal year exports for all main families
./bilan_exercice_precedent.sh
```

## Architecture

All scripts share a common pattern: fetch categories → resolve `"Famille.Categorie"` strings to IDs → fetch transactions → filter/aggregate locally.

- `config.php` — API credentials (`API_KEY`, `COMPANY_ID` constants)
- `_main.php` — shared include: loads `config.php`, `lib/transactions.php`, `lib/familles.php`
- `lib/transactions.php` — `getPennylaneTransactions(array $args)`: fetches all pages from the API using `next_cursor` pagination; supports `filter`, `limit`, `sort`, `cursor`
- `lib/familles.php` — `computeFamilyTotal($transactions, $familyId)`: filters a pre-fetched transaction array by family ID and sums amounts
- `pennylane_exporter.php` — standalone script (does NOT use `_main.php`); has its own `fetchAll()` and category resolution logic; filters by AND-logic on included category IDs and exclusion list
- `bilan_annuel_complet.php` — uses `_main.php`; hardcodes the 9 known family IDs in `$familleIds`; produces full P&L with monthly revenue breakdown and average monthly fixed costs

## API notes

- Base URL: `https://app.pennylane.com/api/external/v2/`
- Endpoints used: `transactions`, `categories`, `category_groups`
- Pagination: `limit=100`, loop on `next_cursor` until `has_more` is false
- Category format used in scripts: `"GroupLabel.CategoryLabel"` (constructed at runtime from API data)
- Transaction amounts: expenses are **negative**, income is **positive** in Pennylane
- `bilan_annuel_complet.php` uses the reusable `getPennylaneTransactions()` from `lib/`; `pennylane_exporter.php` uses its own inline `fetchAll()` — there is intentional duplication between these two scripts
