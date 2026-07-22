# AGENTS.md — POS Krupuk

## Stack

- **Laravel 12** (PHP ^8.2) + **Livewire 4** (single-page, no JS framework)
- **Tailwind CSS 4** via `@tailwindcss/vite` plugin
- **Vite 7** builder; entrypoints: `resources/css/app.css` + `resources/js/app.js`
- **MySQL** (default; `.env.example` shows SQLite fallback)
- **PHPUnit 11** via `php artisan test`

## Architecture

| Layer | Path | Notes |
|---|---|---|
| Route | `routes/web.php:10` | Single app route: `Route::get('/pos', PosApp::class)` |
| Livewire component | `app/Livewire/Pos/PosApp.php` | ~950 lines, all app logic |
| View | `resources/views/livewire/pos/pos-app.blade.php` | All 4 tabs in one file |
| Layout | `resources/views/layouts/app.blade.php` | Inline CSS, font imports, `@livewireStyles`/`@livewireScripts` |
| Models (11) | `app/Models/` | Product, Sale, SaleItem, OilStock, StockManagement, StockRemaining, CustomerLedger, PersonalLedger, SaldoDeduction, Expense, User |
| Helper | `app/helpers.php` | `rupiah()` function, autoloaded via composer.json `files` |
| Migrations | `database/migrations/` | 9 custom + 4 Laravel standard + 2 new |

## Tabs & data scoping

| Tab | Data scope | Contents |
|---|---|---|
| Transaksi Harian | Per-day (date input) | Sales form, expenses form, daily sales table, daily/monthly recap |
| Master Produk | Global (all time) | Product CRUD, seed defaults |
| Hasil | Per-month + global | 4 sections: hutang pelanggan (LIFO matrix, all time), stok barang (monthly: minyak, mgmt with batches, sisa), hutang pribadi (monthly), pengurangan saldo (monthly) |
| Ringkasan | Date-picker for daily, else monthly | Daily asset summary, monthly total summary, reset buttons |

## Key logic

- **Round total**: `Sale::roundTotal(int $total)` — remainder < 500 rounds down, >= 500 rounds up to nearest 1000
- **Save indicators**: On form, "Pas" button fills `txPaid` = `txRoundedTotal`
- **LIFO debt calc**: `processCustomerDebts(name)` in PosApp.php — payments apply to newest debts first
- **Stock mgmt batches**: `StockManagement.batches` JSON column stores `[{id, date, price, sacks: [kg, kg, ...]}]`
- **Expenses**: Separate `expenses` table, per-day, used in daily/monthly cash recap

## Commands

```bash
# Full setup (first time)
composer setup

# Dev (servers + queue + logs + Vite concurrently)
composer dev

# Test (config:clear first, then test suite)
composer test

# Single test file
php artisan test tests/Feature/PosPageTest.php

# PHP linter
vendor/bin/pint
```

## Key quirks

- **No authentication** — `/pos` is open; no login/logout scaffold
- **Session + cache + queue all use database** — migrations create these tables
- **`composer dev`** runs 4 processes concurrently via `concurrently`: artisan serve, queue:listen, pail (logs), and Vite dev
- **Tests use MySQL (`pos_krupuk_testing` DB)** — see `phpunit.xml:26-30`
- **`composer test`** always runs `config:clear` first (see `composer.json:52`)
- **Blade + Livewire only** — no Controllers, no Vue/React, no API routes, no broadcasting
- **`db:seed` not used** — seed products via "Isi contoh nama produk dari catatan" button
- **Month switching** (`activeMonth` property) scopes stock, personal ledger, saldo deductions; customer ledger is global
- **Inline editable cells** use Alpine.js `x-data` with `$wire` calls to persist changes (debt matrix cells, stock detail toggle)

## New migrations (2026-07-22)

1. `add_fields_to_stock_tables` — adds `date` to oil_stocks/stock_managements/stock_remainings, `batches` JSON to stock_managements, `is_paid_btn_clicked` to sales, `date`+`note` to saldo_deductions, creates `expenses` table
2. `drop_stock_management_table` — drops the empty/duplicate `stock_management` table
