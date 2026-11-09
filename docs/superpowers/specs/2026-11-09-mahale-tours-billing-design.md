# Mahale Tours and Travels — Billing App Design Spec

**Date:** 2026-11-09
**Status:** Draft for review
**Owner:** Shubham

## 1. Purpose

Mahale Tours and Travels rents 6–7 cars with drivers. Customers often ask for a bill.
Goal: after a trip, the owner opens the app on his phone, enters trip details in under a
minute, and sends a clean branded PDF bill to the customer on WhatsApp or email.

## 2. Users and scope

### Version 1
- Users: owner and staff (1–2 people). Customers do **not** log in; they only receive bills.
- Masters: cars, drivers, customers.
- Trips, bills (GST or non-GST per bill), multiple payments per trip (advance + balance).
- PDF bill and advance receipt; share via phone share sheet, WhatsApp link, Gmail.
- Reports: earnings per car, GST summary (Excel), outstanding.

### Out of scope for v1 (designed for, not built)
- Customer login and self-booking (`customer` role exists in DB; no screens).
- Play Store / native app (can be added later via Sanctum API + Capacitor).
- Marathi or other languages (English only).

## 3. Architecture

- **Approach:** single Laravel monolith served as a web portal and an installable PWA.
- **Stack:** Laravel (latest stable), Inertia.js + Vue 3, Bootstrap 5, MySQL 8,
  barryvdh/laravel-dompdf (PDF), maatwebsite/excel (Excel export), a QR library for UPI QR.
- **PWA:** web app manifest + service worker caching the app shell only. Data needs the
  server (no offline bill creation in v1).
- **Units:**
  - `BillCalculator` (pure PHP service): input trip + rates + extras + GST settings → line
    items and totals. No DB access. Single source of truth for all money maths.
  - `InvoiceNumberService`: issues the next number per financial year inside a DB transaction
    with row lock.
  - `InvoicePdfService`: renders invoice / receipt PDF from stored data.
  - `ShareService`: share tokens, WhatsApp message text, email dispatch, share log.
  - Controllers stay thin; Vue pages per screen.

## 4. Data model

| Table | Key fields |
|---|---|
| `business_settings` (single row) | name, address, phone, email, logo_path, gstin, state, sac_code, gst_rate (default 5.00), min_km_per_day (default 0), invoice_prefix (default `MTT`), bank_details, upi_id, terms |
| `users` | name, email, phone, password, role (`owner`/`staff`/`customer`) |
| `cars` | registration_no (unique), model, type, seats, rate_per_km, rate_per_day, is_active |
| `drivers` | name, phone, licence_no, is_active |
| `customers` | name, phone, email, address, state, gstin (nullable) |
| `trips` | customer_id, car_id, driver_id, pickup, drop, start_at, end_at, trip_type (`per_km`/`package`), start_km, end_km, rates & extras inputs (json), status (`booked`/`ongoing`/`completed`/`cancelled`) |
| `invoices` | trip_id, invoice_no (unique), financial_year, invoice_date, gst_enabled, tax_type (`cgst_sgst`/`igst`/`none`), subtotal, taxable_amount, cgst, sgst, igst, round_off, total, pdf_path, share_token (unique, 40+ random chars), share_enabled, locked_at, status (`active`/`cancelled`), revision_of_id (nullable) |
| `invoice_items` | invoice_id, description, qty, rate, amount, is_taxable, sort_order |
| `payments` | trip_id, amount, paid_on, mode (`cash`/`upi`/`bank`), reference, note |
| `share_logs` | invoice_id or payment_id, channel (`share_sheet`/`whatsapp_link`/`email`), recipient, shared_at |
| `invoice_sequences` | financial_year, prefix, last_number |

Rules:
- Payments link to the **trip**, so advances can be taken before an invoice exists.
- Balance due = invoice total − sum(payments). Payment status (`unpaid`/`partly_paid`/`paid`)
  is computed, never stored manually.
- All money stored as `decimal(12,2)`.

## 5. Billing calculation (`BillCalculator`)

- `days` = number of calendar days spanned by start_at..end_at (min 1).
- `actual_km` = end_km − start_km (must be ≥ 0, else validation error).

**Per km trip**
- `billable_km` = max(actual_km, min_km_per_day × days).
- Fare line: billable_km × rate_per_km.

**Package trip** (per trip inputs: package_amount OR rate_per_day × days, included_km, included_hours, extra_km_rate, extra_hour_rate)
- Base line: package amount.
- Extra km line if actual_km > included_km: (actual_km − included_km) × extra_km_rate.
- Extra hours line if trip hours > included_hours: (hours − included_hours) × extra_hour_rate (hours rounded up).

**Common extras**
- Taxable: driver allowance (per day × days), night halt (per night × nights), other taxable charge.
- Non-taxable: toll, parking, state permit (actual amounts).
- Optional discount (reduces taxable amount).

**GST** (when gst_enabled)
- Applied only to taxable lines, at `gst_rate` from settings (default 5%).
- Customer state = business state (Maharashtra) → CGST = SGST = rate/2; else IGST = rate.
- Customer state missing → assume intra-state.

**Totals**
- total = taxable + tax + non-taxable; round to nearest rupee; difference shown as "Round off".

## 6. Invoice lifecycle

1. Trip created (booked) → advance payments may be recorded → advance receipt can be shared.
2. Trip completed → invoice generated: number from `InvoiceNumberService`
   (`{prefix}/{FY}/{0001}`, e.g. `MTT/2026-27/0001`, FY = April–March), items from `BillCalculator`, PDF rendered.
3. Invoice editable until first shared; on first share `locked_at` is set.
4. Locked invoice changes: cancel and reissue (new number, `revision_of_id` set, old marked
   `cancelled`). Numbers are never reused or deleted.

## 7. PDF content

A4, English. Header (logo, name, address, phone, GSTIN when GST), title "TAX INVOICE" or
"BILL", invoice no/date/trip period, Bill To (name, phone, address, GSTIN + state for GST),
trip block (car no & model, driver, pickup → drop, start/end time, start/end km, total km),
item table, subtotal, CGST/SGST or IGST, round off, grand total, amount in words, payments
block (advance, other payments, balance due or PAID stamp), bank/UPI details + UPI QR for
balance, terms, signature line.

Advance receipt: small PDF with receipt no, customer, trip, amount, mode, date.

## 8. Sharing

- **Share sheet:** `navigator.share({ files: [pdf] })` when `navigator.canShare` supports files.
- **WhatsApp link:** `https://wa.me/91{phone}?text=...` with message containing customer name,
  invoice no, total, and public link. Used when file share unsupported or on desktop.
- **Public link:** `/b/{share_token}` shows/downloads that PDF only; 404 when disabled.
- **Email:** Laravel Mail via Gmail SMTP (app password), PDF attached.
- Every share writes a `share_logs` row.

## 9. Screens

Login · Dashboard (today/upcoming trips, pending amount, month earnings, "+ New Bill") ·
Trips/Bills list (search, status & date filters) · New/Edit trip & bill (live preview via
calculator endpoint) · Bill detail (PDF preview, payments, share buttons, share log) ·
Cars · Drivers · Customers (with ledger) · Reports · Settings. Mobile-first Bootstrap layout.

## 10. Reports

- Earnings per car per month: trips, km, revenue.
- GST summary for date range: invoice no, date, customer, GSTIN, taxable, CGST, SGST, IGST, total → Excel.
- Outstanding: customer, invoice, total, paid, balance, age in days.

## 11. Security

- Session auth (email + password), remember-me, login rate limit.
- Policies: only `owner`/`staff` access the app; `owner` alone can edit Settings and users.
- HTTPS everywhere; share tokens are unguessable and revocable.

## 12. Hosting and operations

- Oracle Cloud Always Free ARM VM, Ubuntu LTS, Nginx, PHP-FPM, MySQL 8, Let's Encrypt.
- Account upgraded to Pay-As-You-Go (avoids idle reclamation) with a ₹1 billing alert.
- `deploy.sh`: git pull, composer install --no-dev, migrate --force, npm build, cache config/routes/views.
- `server-setup.sh` provisions a fresh Ubuntu server (same script = migration plan to any host).
- Daily cron backup (mysqldump + PDFs) to Google Drive via rclone, 30-day retention; documented restore steps.
- UptimeRobot monitoring with email/Telegram alert.
- Code in a private GitHub repo.

## 13. Testing

- Unit: `BillCalculator` — per km, min km rule, package with/without extras, extra hours rounding,
  non-taxable lines, discount, intra/inter-state GST, GST off, round off, negative km rejected.
- Feature: invoice numbering per FY (incl. April rollover, concurrency), payment status,
  lock on share, cancel & reissue, public link enable/disable, auth/role access, GST Excel export.

## 14. Open items to confirm with the owner (do not block build)

- Business GSTIN and that GST rate is 5% (CA to confirm).
- Minimum km per day value, if any.
- Whether toll/parking are billed as non-taxable reimbursements (CA to confirm).
