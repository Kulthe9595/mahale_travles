# Mahale Tours Billing App Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Laravel web portal + installable PWA where the owner records trips, generates GST/non-GST bills and advance receipts as PDFs, tracks payments, shares bills on WhatsApp/email, and exports reports.

**Architecture:** One Laravel monolith with Inertia + Vue 3 pages styled with Bootstrap 5. All money maths lives in a pure `BillCalculator`; invoice numbering, PDF rendering and sharing are separate services. Deployed to an Oracle Always Free Ubuntu VM by shell scripts that also serve as the migration plan.

**Tech Stack:** PHP ≥ 8.3, latest stable Laravel (`composer create-project laravel/laravel`), MySQL 8 (SQLite in-memory for tests), inertiajs/inertia-laravel + @inertiajs/vue3, Vue 3, Bootstrap 5, barryvdh/laravel-dompdf, maatwebsite/excel, chillerlan/php-qrcode, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-09-mahale-tours-billing-design.md`

## Global Constraints

- App timezone `Asia/Kolkata`; all dates (FY, invoice date, days) computed in this zone.
- Financial year runs 1 April – 31 March, written `2026-27`.
- Invoice number format `{prefix}/{FY}/{4-digit seq}`, e.g. `MTT/2026-27/0001`; default prefix `MTT`. Numbers are never reused or deleted.
- Money columns are `decimal(12,2)`. Inside `BillCalculator` and services, money is `int` paise; convert only at model/HTTP boundaries via `App\Support\Money`.
- Default `gst_rate` 5.00, default `min_km_per_day` 0, business state `Maharashtra`.
- Taxable lines: kilometre/package charges, extra km, extra hours, driver allowance, night halt, other taxable charge, discount. Non-taxable: toll, parking, state permit.
- Totals round to the nearest rupee, half up; difference shown as "Round off".
- English only. Bootstrap 5 only (remove Tailwind from the skeleton). No paid services.
- Roles: `owner`, `staff`, `customer`. Only owner/staff can log in to the app in v1; only owner edits Settings.

## Review Focus

1. **FY boundary in IST** — a bill dated 31 Mar 23:30 IST belongs to `2025-26`, one dated 1 Apr 00:10 IST to `2026-27` and restarts at `0001`. Test in Task 6.
2. **Phone formats** — customers saved as `98765 43210`, `+91 98765-43210`, `09876543210` must all give WhatsApp number `919876543210`; a missing/invalid phone hides the WhatsApp button instead of building a broken link. Test in Task 9.
3. **Overpayment** — payments exceeding the bill total show status Paid and "Excess received ₹X", never a negative balance due. Test in Task 7.
4. **Double tap on "Generate bill"** — a trip can have only one active invoice; the second request gets a validation error, not a second number. Test in Task 6.
5. **Overnight trips** — 9 Nov 22:00 → 10 Nov 06:00 counts as 2 days and 1 night for allowance, night halt and minimum km. Test in Task 3.

---

## File Structure

```
app/
  Support/Money.php                    paise <-> decimal string, rupee formatting (₹8,450.00), Indian words
  Support/Phone.php                    WhatsApp number normalization
  Enums/{Role,TripType,TripStatus,PaymentMode,PaymentStatus,TaxType,ShareChannel}.php
  Billing/BillInput.php                calculator input DTO
  Billing/BillLine.php                 one computed line
  Billing/BillResult.php               calculator output DTO (+ toArray)
  Billing/BillCalculator.php           pure maths, no DB
  Services/InvoiceNumberService.php    FY + sequence
  Services/InvoiceService.php          generate / refresh / lock / cancelAndReissue
  Services/InvoicePdfService.php       invoice + receipt PDF
  Services/ShareService.php            tokens, WhatsApp URL, email, share log
  Services/ReportService.php           earnings, GST summary, outstanding
  Exports/GstSummaryExport.php
  Mail/InvoiceMail.php
  Models/{User,BusinessSetting,Car,Driver,Customer,Trip,Invoice,InvoiceItem,Payment,ShareLog,InvoiceSequence}.php
  Http/Middleware/EnsureAppUser.php
  Http/Controllers/...                 thin, one per resource
resources/js/Pages/...                 Vue pages per screen
resources/views/pdf/{invoice,receipt}.blade.php
deploy/{server-setup.sh,deploy.sh,backup.sh}
docs/DEPLOY.md
tests/Unit/..., tests/Feature/...
```

---

### Task 1: Skeleton with Inertia, Vue 3, Bootstrap and test setup

**Files:**
- Create: Laravel project at repo root; `resources/js/app.js`, `resources/js/Layouts/AppLayout.vue`, `resources/views/app.blade.php`, `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `config/app.php` (timezone), `phpunit.xml` (sqlite memory), `vite.config.js`, `package.json`
- Test: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Produces: `AppLayout.vue` (top navbar: Dashboard, Bills, Cars, Drivers, Customers, Reports, Settings, Logout; mobile collapse), Inertia shared props `auth.user {id,name,role}` and `flash.success`.

- [ ] **Step 1:** `composer create-project laravel/laravel .` (keep existing README/docs), `composer require inertiajs/inertia-laravel`, `npm i vue @inertiajs/vue3 @vitejs/plugin-vue bootstrap @popperjs/core`, remove Tailwind packages and imports. Set `'timezone' => 'Asia/Kolkata'`. In `phpunit.xml` set `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.
- [ ] **Step 2: Write the failing test** `SmokeTest::test_guest_is_redirected_to_login` — `$this->get('/')->assertRedirect('/login')`; `test_timezone_is_ist` — `assertSame('Asia/Kolkata', config('app.timezone'))`.
- [ ] **Step 3:** Run `php artisan test --filter=SmokeTest` → FAIL (route `/` is the welcome page).
- [ ] **Step 4:** Wire Inertia root view and `HandleInertiaRequests`; make `/` an `auth`-protected route rendering `Dashboard` (placeholder page); add `/login` route stub returning the `Auth/Login` page.
- [ ] **Step 5:** Run `php artisan test` → PASS; `npm run build` → succeeds with no Tailwind references.
- [ ] **Step 6: Commit** `git commit -am "chore: laravel skeleton with inertia, vue 3 and bootstrap"`

### Task 2: Auth, roles and business settings

**Files:**
- Create: `app/Enums/Role.php`, `app/Http/Middleware/EnsureAppUser.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Models/BusinessSetting.php`, `app/Http/Controllers/SettingsController.php`, migrations `add_role_and_phone_to_users`, `create_business_settings_table`, `database/seeders/DatabaseSeeder.php`, `resources/js/Pages/Auth/Login.vue`, `resources/js/Pages/Settings/Edit.vue`
- Test: `tests/Feature/AuthTest.php`, `tests/Feature/SettingsTest.php`

**Interfaces:**
- Produces: `Role` enum (`owner`,`staff`,`customer`); `User::isOwner(): bool`; middleware alias `app.user` (403 for `customer`); Gate `manage-settings` (owner only); `BusinessSetting::current(): BusinessSetting` (creates the single row with defaults if missing); fields per spec §4.

- [ ] **Step 1: Write failing tests**
  - `test_owner_can_log_in_with_email_and_password` → redirect `/`, authenticated.
  - `test_customer_role_gets_403_on_dashboard`.
  - `test_login_is_rate_limited_after_5_failures` → 6th attempt has session error containing "Too many".
  - `test_remember_me_sets_recaller_cookie`.
  - `test_current_settings_have_defaults` → `gst_rate` `'5.00'`, `min_km_per_day` `0`, `invoice_prefix` `'MTT'`, `state` `'Maharashtra'`.
  - `test_staff_cannot_update_settings` (403), `test_owner_updates_settings_and_uploads_logo` (`Storage::fake('public')`, file stored, `logo_path` set).
- [ ] **Step 2:** Run `php artisan test --filter='AuthTest|SettingsTest'` → FAIL.
- [ ] **Step 3:** Implement. Login uses `RateLimiter` keyed by email+IP, 5 attempts/minute. Seeder creates owner from env `OWNER_EMAIL`/`OWNER_PASSWORD` and business name `Mahale Tours and Travels`. Settings validation: `gstin` nullable, regex `/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/`; `gst_rate` 0–28; `min_km_per_day` 0–1000 integer; logo image ≤ 1 MB.
- [ ] **Step 4:** Run tests → PASS.
- [ ] **Step 5: Commit** `feat: login, roles and business settings`

### Task 3: Money helpers and BillCalculator

**Files:**
- Create: `app/Support/Money.php`, `app/Enums/{TripType,TaxType}.php`, `app/Billing/{BillInput,BillLine,BillResult,BillCalculator}.php`
- Test: `tests/Unit/MoneyTest.php`, `tests/Unit/BillCalculatorTest.php`

**Interfaces:**
- Produces:
  - `Money::toPaise(string|int|float $rupees): int`, `Money::toDecimal(int $paise): string` (`"8450.00"`), `Money::format(int $paise): string` (`"₹8,450.00"`, Indian grouping: `₹1,25,000.00`), `Money::inWords(int $paise): string` (`"Rupees Seven Thousand Seven Hundred Forty Three Only"`, lakh/crore system, paise as `"and Fifty Paise"`).
  - `TripType`: `PerKm='per_km'`, `Package='package'`. `TaxType`: `CgstSgst='cgst_sgst'`, `Igst='igst'`, `None='none'`.
  - `BillInput` (readonly, all money in paise): `tripType, startAt (CarbonImmutable), endAt, startKm:int, endKm:int, ratePerKm=0, minKmPerDay=0, packageAmount=0, packageRatePerDay=?int null, includedKm=0, includedHours=0, extraKmRate=0, extraHourRate=0, driverAllowancePerDay=0, nightHaltPerNight=0, otherTaxable=0, otherTaxableLabel='Other charges', toll=0, parking=0, statePermit=0, discount=0, gstEnabled=false, gstRate=5.0, businessState='Maharashtra', customerState=?string null`.
  - `BillLine(description:string, qty:float, rate:int, amount:int, taxable:bool)`.
  - `BillResult(lines:BillLine[], days:int, nights:int, hours:int, actualKm:int, billableKm:int, taxableAmount:int, nonTaxableAmount:int, taxType:TaxType, cgst:int, sgst:int, igst:int, roundOff:int, total:int)` + `toArray(): array` (money as decimal strings).
  - `BillCalculator::calculate(BillInput $in): BillResult` — throws `InvalidArgumentException` for `endKm < startKm`, `endAt < startAt`, or discount greater than taxable amount before discount.

**Rules the tests pin (spec §5):**
- `days` = calendar dates spanned (inclusive), min 1; `nights` = days − 1; `hours` = ceil(minutes/60).
- Lines with zero amount are omitted. Line descriptions: `Kilometre charges`, `Package charges`, `Extra km`, `Extra hours`, `Driver allowance`, `Night halt`, `{otherTaxableLabel}`, `Discount` (negative amount, taxable), `Toll`, `Parking`, `State permit`.
- Tax in paise = round(taxable × rate / 100). CGST = intdiv(tax, 2), SGST = tax − CGST. Intra-state when `customerState` is null or equals `businessState` (case-insensitive, trimmed).

- [ ] **Step 1: Write failing tests**
  - `MoneyTest`: `toPaise('8450.5') === 845050`; `format(12500000) === '₹1,25,000.00'`; `inWords(774300) === 'Rupees Seven Thousand Seven Hundred Forty Three Only'`; `inWords(10000050) === 'Rupees One Lakh and Fifty Paise Only'`.
  - `test_per_km_outstation_with_min_km_and_gst_intra_state`: 2026-11-09 08:00 → 2026-11-10 20:00, km 10000→10300, rate ₹12, min 250/day, allowance ₹300/day, night halt ₹250, toll ₹450, parking ₹100, GST on, customer Maharashtra → days 2, nights 1, billableKm 500, lines Kilometre 500×12=6000.00, Driver allowance 2×300=600.00, Night halt 1×250=250.00, Toll 450.00, Parking 100.00; taxable 6850.00; cgst 171.25; sgst 171.25; total 7743.00; roundOff 0.50.
  - `test_actual_km_used_when_above_minimum`: same trip, km 10000→10620 → billableKm 620, kilometre amount 7440.00.
  - `test_package_with_extra_km_and_hours_no_gst`: 2026-11-09 09:00 → 19:30, km 5000→5120, package ₹2500 incl. 80 km / 8 h, extra km ₹13, extra hour ₹150 → hours 11, lines Package 2500.00, Extra km 40×13=520.00, Extra hours 3×150=450.00; taxType none; total 3470.00; roundOff 0.00.
  - `test_package_per_day_rate`: packageRatePerDay ₹2200, 3-day trip → Package charges qty 3, amount 6600.00.
  - `test_igst_for_other_state`: per-km 100 km × ₹10, GST on, customer `Karnataka` → igst 50.00, cgst 0, total 1050.00.
  - `test_discount_reduces_taxable`: 100 km × ₹10, discount ₹100, GST on intra → taxable 900.00, tax 45.00, total 945.00.
  - `test_overnight_trip_counts_two_days_one_night` (Review Focus 5): 22:00 → next day 06:00, allowance ₹300 → days 2, nights 1, allowance 600.00.
  - `test_rejects_end_km_below_start_km`, `test_rejects_end_before_start`, `test_rejects_discount_above_taxable`.
  - `test_zero_lines_are_omitted`: no toll/parking → no `Toll`/`Parking` lines.
- [ ] **Step 2:** Run `php artisan test --filter='MoneyTest|BillCalculatorTest'` → FAIL.
- [ ] **Step 3:** Implement. Rounding: `total = taxable + tax + nonTaxable`; `rounded = intdiv(total + 50, 100) * 100`; `roundOff = rounded − total`.
- [ ] **Step 4:** Run tests → PASS.
- [ ] **Step 5: Commit** `feat: bill calculator with gst, minimum km and package rules`

### Task 4: Masters — cars, drivers, customers

**Files:**
- Create: migrations, models, `CarController`, `DriverController`, `CustomerController` (resource, no `show` except customer ledger), Vue pages `Cars/Index.vue`, `Cars/Form.vue`, `Drivers/*`, `Customers/Index.vue`, `Customers/Form.vue` (the customer ledger page is built in Task 11, once bills and payments exist)
- Test: `tests/Feature/MastersTest.php`

**Interfaces:**
- Produces: `Car {registration_no unique, model, type, seats, rate_per_km decimal, rate_per_day decimal, is_active}`, `Driver {name, phone, licence_no, is_active}`, `Customer {name, phone, email, address, state default 'Maharashtra', gstin nullable}`; scope `active()` on Car and Driver; `GET /customers/search?q=` returning `[{id,name,phone,state,gstin}]` (max 10, name or phone match) for the bill form.

- [ ] **Step 1: Write failing tests** — `test_owner_creates_car_with_unique_registration` (registration uppercased, spaces stripped: `mh 17 ab 1234` → `MH17AB1234`; duplicate fails validation); `test_inactive_car_hidden_from_active_scope`; `test_driver_crud`; `test_customer_gstin_validated_when_present`; `test_customer_search_matches_phone_digits` (`98765` finds `+91 98765 43210`); `test_cannot_delete_car_used_by_a_trip` (deactivate instead — add this test now as `markTestIncomplete`, enable in Task 5).
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement; store customer phone as entered plus a `phone_digits` column (digits only) for search.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5: Commit** `feat: cars, drivers and customers masters`

### Task 5: Trips

**Files:**
- Create: `app/Enums/TripStatus.php`, migration `create_trips_table`, `app/Models/Trip.php`, `app/Http/Controllers/TripController.php`, `app/Http/Controllers/BillPreviewController.php`, `app/Http/Requests/TripRequest.php`
- Test: `tests/Feature/TripTest.php`

**Interfaces:**
- Consumes: `BillInput`, `BillCalculator`, `BusinessSetting::current()`, `Money`.
- Produces: `Trip {customer_id, car_id, driver_id nullable, pickup, drop, start_at, end_at nullable, trip_type, start_km nullable, end_km nullable, billing_inputs json, status}`; `TripStatus` (`booked`,`ongoing`,`completed`,`cancelled`); `Trip::toBillInput(BusinessSetting $s): BillInput` (merges `billing_inputs` rupee values → paise, `gstEnabled` from `billing_inputs.gst_enabled`, customer state); `POST /bill-preview` → `BillCalculator` result `toArray()` or 422 with the exception message.

- [ ] **Step 1: Write failing tests** — `test_trip_can_be_booked_without_end_details` (status booked, end_km null); `test_rates_default_from_car` (form props include car `rate_per_km`); `test_preview_returns_same_totals_as_calculator` (post the Task 3 per-km example → `total` `"7743.00"`); `test_preview_returns_422_when_end_km_below_start`; enable `test_cannot_delete_car_used_by_a_trip` from Task 4.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement. `TripRequest` requires `end_at`/`end_km` only when status is `completed`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5: Commit** `feat: trips and live bill preview endpoint`

### Task 6: Invoice numbering and invoice lifecycle

**Files:**
- Create: migrations `create_invoice_sequences_table`, `create_invoices_table`, `create_invoice_items_table`; models `InvoiceSequence`, `Invoice`, `InvoiceItem`; `app/Services/InvoiceNumberService.php`, `app/Services/InvoiceService.php`, `app/Http/Controllers/InvoiceController.php`
- Test: `tests/Unit/InvoiceNumberServiceTest.php`, `tests/Feature/InvoiceLifecycleTest.php`

**Interfaces:**
- Consumes: `Trip::toBillInput`, `BillCalculator`.
- Produces:
  - `InvoiceNumberService::financialYear(CarbonImmutable $date): string` (`'2026-27'`); `next(CarbonImmutable $date): string` (transaction + `lockForUpdate` on the `(financial_year, prefix)` row).
  - `Invoice` fields per spec §4 except `pdf_path` (PDFs are rendered on demand so the payment block is always current; the DB backup reproduces every PDF). `share_token` = `Str::random(48)` on create. Relations `trip`, `items`, `revisionOf`. Scope `active()`.
  - `InvoiceService::generate(Trip $trip): Invoice` — fails with `ValidationException` (`trip` key, "This trip already has a bill.") if an active invoice exists; sets trip status `completed`.
  - `InvoiceService::refresh(Invoice $invoice): Invoice` — recompute items/totals in place; throws if `locked_at` is set.
  - `InvoiceService::lock(Invoice $invoice): void` — sets `locked_at` once.
  - `InvoiceService::cancelAndReissue(Invoice $invoice): Invoice` — old → `cancelled`, new invoice with new number and `revision_of_id`.

- [ ] **Step 1: Write failing tests**
  - `test_numbers_increment_within_fy`: two calls on 2026-11-09 → `MTT/2026-27/0001`, `MTT/2026-27/0002`.
  - `test_fy_boundary_in_ist` (Review Focus 1): `2026-03-31 23:30 Asia/Kolkata` → `2025-26`; `2026-04-01 00:10` → `2026-27/0001` even after earlier numbers in 2025-26.
  - `test_prefix_change_starts_its_own_series`.
  - `test_generate_creates_items_matching_calculator` (Task 3 per-km example: 5 items, total 7743.00, tax_type `cgst_sgst`).
  - `test_second_generate_for_same_trip_fails` (Review Focus 4).
  - `test_refresh_blocked_after_lock`.
  - `test_cancel_and_reissue_keeps_old_number_and_links_revision`: old status cancelled, new number `0002`, `revision_of_id` = old id; `generate` afterwards still fails (new invoice is active).
- [ ] **Step 2:** Run `php artisan test --filter='InvoiceNumberServiceTest|InvoiceLifecycleTest'` → FAIL.
- [ ] **Step 3:** Implement; add a partial-uniqueness guard in code (check inside the same transaction with `lockForUpdate` on the trip row) since MySQL lacks partial unique indexes.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5: Commit** `feat: fy-wise invoice numbering and invoice lifecycle`

### Task 7: Payments and payment status

**Files:**
- Create: `app/Enums/{PaymentMode,PaymentStatus}.php`, migration `create_payments_table`, `app/Models/Payment.php`, `app/Http/Controllers/PaymentController.php`
- Modify: `app/Models/Trip.php`
- Test: `tests/Feature/PaymentTest.php`

**Interfaces:**
- Produces: `Payment {trip_id, amount, paid_on, mode, reference, note}`, `Payment::receiptNumber(): string` (`RCPT-00001`); `Trip::paidPaise(): int`; `Trip::balanceDuePaise(): ?int` (null without active invoice, never negative); `Trip::excessPaise(): int`; `Trip::paymentStatus(): ?PaymentStatus` (`unpaid`/`partly_paid`/`paid`; null without invoice).

- [ ] **Step 1: Write failing tests** — `test_advance_before_invoice` (₹2000 on a booked trip → paid 2000.00, balanceDue null, status null); `test_status_moves_unpaid_partly_paid_paid` (invoice 7743.00; 0 → unpaid, 2000 → partly_paid balance 5743.00, +5743 → paid); `test_overpayment_shows_excess` (Review Focus 3: pay 8000 → paid, balance 0, excess 257.00); `test_payment_amount_must_be_positive`; `test_payment_can_be_deleted_by_owner_only`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5: Commit** `feat: payments with computed status`

### Task 8: Invoice and receipt PDFs

**Files:**
- Create: `app/Services/InvoicePdfService.php`, `resources/views/pdf/invoice.blade.php`, `resources/views/pdf/receipt.blade.php`, `app/Http/Controllers/PdfController.php`
- Test: `tests/Feature/PdfTest.php`

**Interfaces:**
- Consumes: `Invoice`, `Payment`, `Money::format/inWords`, `BusinessSetting::current()`.
- Produces: `InvoicePdfService::invoice(Invoice $i): \Barryvdh\DomPDF\PDF`, `receipt(Payment $p): PDF`, `invoiceFilename(Invoice $i): string` (`MTT-2026-27-0001.pdf`); `InvoicePdfService::upiUri(Invoice $i): ?string` (`upi://pay?pa={upi_id}&pn={name}&am={balance}&cu=INR&tn={invoice_no}`, null when no UPI id or no balance); routes `GET /invoices/{invoice}/pdf`, `GET /payments/{payment}/receipt` (auth).

- [ ] **Step 1: Write failing tests** (assert on rendered HTML via `view('pdf.invoice', ...)->render()` plus a smoke `->output()` starting with `%PDF`):
  - `test_gst_invoice_shows_tax_invoice_title_gstin_and_split` ("TAX INVOICE", business GSTIN, "CGST @ 2.5%", "₹171.25").
  - `test_non_gst_bill_has_bill_title_and_no_tax_rows`.
  - `test_shows_amount_in_words_and_round_off`.
  - `test_payment_block_shows_advance_and_balance` ("Advance received", "Balance due", "₹5,743.00"); `test_paid_stamp_when_fully_paid`.
  - `test_cancelled_invoice_shows_cancelled_stamp`.
  - `test_upi_uri_contains_balance_and_invoice_no`; `test_no_qr_when_paid`.
  - `test_receipt_shows_amount_mode_and_receipt_number`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement layout per spec §7; QR via chillerlan/php-qrcode as a data-URI `<img>`; DejaVu Sans font so `₹` renders.
- [ ] **Step 4:** Run → PASS; open one generated PDF and check it visually on A4.
- [ ] **Step 5: Commit** `feat: invoice and advance receipt pdfs`

### Task 9: Sharing — public link, WhatsApp, email, share log

**Files:**
- Create: `app/Support/Phone.php`, `app/Enums/ShareChannel.php`, migration `create_share_logs_table`, `app/Models/ShareLog.php`, `app/Services/ShareService.php`, `app/Mail/InvoiceMail.php`, `resources/views/mail/invoice.blade.php`, `app/Http/Controllers/ShareController.php`, `app/Http/Controllers/PublicBillController.php`
- Test: `tests/Unit/PhoneTest.php`, `tests/Feature/ShareTest.php`

**Interfaces:**
- Consumes: `InvoicePdfService`, `InvoiceService::lock`.
- Produces: `Phone::toWhatsApp(?string $raw): ?string`; `ShareService::publicUrl(Invoice): string` (`/b/{share_token}`); `ShareService::whatsappUrl(Invoice): ?string`; `ShareService::message(Invoice): string`; `ShareService::email(Invoice, string $to): void`; `ShareService::log(Invoice|Payment $target, ShareChannel $channel, ?string $recipient): ShareLog` (also locks an invoice on first log); routes `POST /invoices/{invoice}/share` `{channel}`, `POST /invoices/{invoice}/email` `{to}`, `POST /invoices/{invoice}/link` `{enabled}`, public `GET /b/{token}`.
- Exact message copy: `Dear {customer name}, your bill {invoice_no} for {Money::format(total)} from {business name}: {public url}`.

- [ ] **Step 1: Write failing tests**
  - `PhoneTest` (Review Focus 2): `'98765 43210'`, `'+91 98765-43210'`, `'09876543210'`, `'919876543210'` → `'919876543210'`; `''`, `null`, `'12345'` → null.
  - `test_whatsapp_url_encodes_message` → starts with `https://wa.me/919876543210?text=Dear%20`.
  - `test_whatsapp_url_null_without_valid_phone`.
  - `test_public_link_streams_pdf_without_login`; `test_public_link_404_when_disabled`; `test_unknown_token_404`.
  - `test_first_share_locks_invoice_and_logs` (channel `whatsapp_link`); `test_email_sends_with_pdf_attachment` (`Mail::fake`, attachment named `MTT-2026-27-0001.pdf`, logs channel `email`).
  - `test_receipt_share_logs_against_payment`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement. Mail uses default mailer configured via `.env` (Gmail SMTP app password documented in `docs/DEPLOY.md`).
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5: Commit** `feat: sharing via public link, whatsapp, email and share log`

### Task 10: Bill screens (new bill, list, detail)

**Files:**
- Create: `resources/js/Pages/Trips/Index.vue`, `Trips/Form.vue`, `Trips/Show.vue`, `resources/js/Components/{CustomerPicker,BillPreview,PaymentForm,ShareButtons,StatusBadge}.vue`
- Modify: `TripController`, `InvoiceController` (Inertia responses)
- Test: `tests/Feature/BillScreensTest.php`

**Interfaces:**
- Consumes: `/customers/search`, `/bill-preview`, invoice/payment/share routes from Tasks 5–9.
- `ShareButtons.vue`: if `navigator.canShare?.({files:[file]})` → fetch `/invoices/{id}/pdf` as blob, `navigator.share({files:[new File([blob], filename, {type:'application/pdf'})], text: message})`, then `POST share {channel:'share_sheet'}`; else show "WhatsApp" link (`whatsappUrl`, hidden when null), "Email", "Copy link".

- [ ] **Step 1: Write failing tests** (Inertia assertions) — `test_index_filters_by_payment_status_and_search` (`?status=partly_paid`, `?q=MH17` matches car, customer, invoice no); `test_show_page_props_include_totals_payments_share_log_and_whatsapp_url`; `test_form_props_include_active_cars_and_drivers_only`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement pages, mobile-first: form sections Customer → Car & driver → Trip → Charges (fields switch by trip type) → GST toggle → sticky bottom bar with live total (debounced 400 ms preview) and Save. Create customer inline from `CustomerPicker`.
- [ ] **Step 4:** Run tests → PASS; `npm run build`; manually create the Task 3 per-km bill in a 390 px wide browser and confirm total ₹7,743.00 and the share sheet opens on an Android phone.
- [ ] **Step 5: Commit** `feat: bill create, list and detail screens`

### Task 11: Dashboard and reports

**Files:**
- Create: `app/Services/ReportService.php`, `app/Exports/GstSummaryExport.php`, `app/Http/Controllers/{DashboardController,ReportController}.php`, `resources/js/Pages/Dashboard.vue`, `resources/js/Pages/Reports/{Index,Earnings,Gst,Outstanding}.vue`, `resources/js/Pages/Customers/Show.vue` (ledger)
- Test: `tests/Feature/ReportTest.php`

**Interfaces:**
- Produces: `ReportService::earningsByCar(CarbonImmutable $from, CarbonImmutable $to): Collection<{car, trips:int, km:int, revenue_paise:int}>` (active invoices by `invoice_date`; revenue = invoice total); `gstSummary($from, $to): Collection<{invoice_no, invoice_date, customer, gstin, taxable, cgst, sgst, igst, total}>` (active GST invoices only); `outstanding(): Collection<{customer, invoice_no, total, paid, balance, age_days}>` (balance > 0, oldest first); `dashboard(): array{today_trips, upcoming_trips, pending_paise, month_revenue_paise}`; `GET /reports/gst/export?from=&to=` → `gst-summary-{from}-{to}.xlsx`.

- [ ] **Step 1: Write failing tests** — `test_earnings_group_by_car_and_skip_cancelled`; `test_gst_summary_excludes_non_gst_and_cancelled`; `test_gst_export_downloads_xlsx` (`Excel::fake()`, `assertDownloaded`); `test_outstanding_sorted_oldest_first_and_excludes_paid`; `test_dashboard_pending_sums_balances`; `test_customer_ledger_lists_bills_with_balance` (`GET /customers/{id}` props: each active invoice with total, paid, balance, and the customer's total outstanding).
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5: Commit** `feat: dashboard and reports with gst excel export`

### Task 12: PWA install

**Files:**
- Create: `public/manifest.webmanifest`, `public/sw.js`, `public/icons/icon-192.png`, `public/icons/icon-512.png`
- Modify: `resources/views/app.blade.php`, `resources/js/app.js`
- Test: `tests/Feature/PwaTest.php`

**Interfaces:**
- Manifest: `name` "Mahale Tours and Travels", `short_name` "Mahale Tours", `start_url` "/", `display` "standalone", theme colour from the layout. Service worker caches only built assets (`/build/*`) and icons; network-only for everything else.

- [ ] **Step 1: Write failing test** — `test_manifest_is_served_and_linked` (`/manifest.webmanifest` 200 with `short_name`; app layout HTML contains `rel="manifest"`).
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement; register SW in `app.js` only in production builds.
- [ ] **Step 4:** Run → PASS; Chrome DevTools Lighthouse "Installable" passes on the deployed site (checked after Task 13).
- [ ] **Step 5: Commit** `feat: installable pwa`

### Task 13: Deployment, backups and docs

**Files:**
- Create: `deploy/server-setup.sh`, `deploy/deploy.sh`, `deploy/backup.sh`, `docs/DEPLOY.md`
- Modify: `README.md`

**Interfaces:**
- `server-setup.sh <domain> <email>`: Ubuntu LTS ARM — Nginx, PHP-FPM + extensions (mbstring, xml, gd, zip, intl, mysql, bcmath), MySQL 8, Composer, Node LTS, certbot, ufw (22/80/443), cron for `schedule:run`, swap file.
- `deploy.sh`: `git pull --ff-only`, `composer install --no-dev -o`, `php artisan migrate --force`, `npm ci && npm run build`, `php artisan optimize`, reload php-fpm. Exits non-zero on any failure (`set -euo pipefail`).
- `backup.sh`: `mysqldump --single-transaction` + `storage/app/public` → dated tar.gz → `rclone copy` to `gdrive:mahale-backups`, delete remote files older than 30 days; scheduled daily 02:30 IST.
- `DEPLOY.md`: Oracle signup, Pay-As-You-Go upgrade + ₹1 budget alert, VM shape (Ampere A1, 1 OCPU / 6 GB is enough), security list ports, DNS, Gmail app password `.env` keys, rclone Google Drive setup, UptimeRobot, restore steps, moving to another host.

- [ ] **Step 1:** Write scripts; run `shellcheck deploy/*.sh` → no errors.
- [ ] **Step 2:** Dry-run `server-setup.sh` and `deploy.sh` in a fresh `ubuntu:24.04` Docker container (arm64 if available) → app responds 200 on `/login`.
- [ ] **Step 3:** Run `backup.sh` with an rclone `local` remote → archive created and contains `database.sql`; follow the restore steps into an empty DB → row counts match.
- [ ] **Step 4:** Run full suite `php artisan test` → all PASS.
- [ ] **Step 5: Commit** `chore: deployment scripts, backups and docs`
