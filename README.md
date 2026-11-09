# Mahale Tours and Travels — Billing

Web portal + installable phone app (PWA) for a car-rental-with-driver business: record trips,
create GST or non-GST bills, take advances and balance payments, and share PDF bills on
WhatsApp or email. Reports: earnings per car, GST summary (Excel) and outstanding.

**Stack:** Laravel 13 · Inertia + Vue 3 · Bootstrap 5 · MySQL (SQLite for tests) · DomPDF.

## Run locally

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
OWNER_EMAIL=you@example.com OWNER_PASSWORD=secret123 php artisan db:seed
npm run build        # or `npm run dev` while working on the UI
php artisan serve
```

Open <http://127.0.0.1:8000> and log in with the owner email/password.

## Tests

```bash
php artisan test
```

All bill maths lives in `app/Billing/BillCalculator.php` with exact-value tests in
`tests/Unit/BillCalculatorTest.php`. Change the maths there and the screen preview,
saved bills and PDFs all follow.

## Where things are

| What | Where |
|---|---|
| Bill calculation | `app/Billing/` |
| Invoice numbers (per financial year) | `app/Services/InvoiceNumberService.php` |
| Create / refresh / lock / reissue bills | `app/Services/InvoiceService.php` |
| PDF bill and receipt | `app/Services/InvoicePdfService.php`, `resources/views/pdf/` |
| WhatsApp / email / share log | `app/Services/ShareService.php` |
| Reports | `app/Services/ReportService.php` |
| Screens | `resources/js/Pages/` |
| Deploy, backups | `deploy/`, `docs/DEPLOY.md` |
| Design and plan | `docs/superpowers/` |

## Deploy

See [docs/DEPLOY.md](docs/DEPLOY.md): Oracle Cloud Always Free, HTTPS, daily Google Drive
backups, uptime alerts, one-command updates.
