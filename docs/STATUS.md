# Projektstatus — Appointment Scheduler

> **Gedächtnis-Datei:** Diese Datei ist die kanonische Übersicht über Struktur, Fähigkeiten und aktuellen Stand der Anwendung. Nach jeder relevanten Änderung (Feature, Migration, Route, Rolle, Billing, etc.) aktualisieren.

| Meta | Wert |
|------|------|
| **Branch** | `customersearch` (KI-Kundensuche) |
| **Letztes Update** | 2026-06-23 |
| **Letzter Commit** | uncommitted — Kundensuche auf Branch `customersearch` |

---

## Tech-Stack

| Schicht | Technologie |
|---------|-------------|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | React 19 + TypeScript + Inertia.js v2, Vite 7 |
| UI | Tailwind CSS v4, shadcn/Radix-Komponenten |
| Datenbank | PostgreSQL 16 |
| Cache / Queue / Session | Redis + Laravel Horizon |
| Auth | Laravel Breeze (Session), E-Mail-Verifizierung |
| Rollen | spatie/laravel-permission |
| KI | xAI/Grok via `grok-php/laravel` |
| Billing | Laravel Cashier v16 (Stripe) |

---

## Projektstruktur

```
app/
├── AI/              → Grok-Terminplanung, System-Prompts
├── Console/         → Artisan-Commands (z.B. fällige Termine planen)
├── DTOs/            → Scheduling-Datenobjekte
├── Enums/           → Termin- & Verhandlungsstatus
├── Http/
│   ├── Controllers/ → Admin, Billing, Public, Auth
│   └── Middleware/  → Mandant, Abo-Check, Inertia
├── Jobs/            → Queue: Scheduling, E-Mail, Stripe-Sync
├── Models/          → Eloquent + BelongsToCompany-Trait
├── Observers/       → Billing-Nutzungssync (Staff/Customer)
├── Policies/        → Berechtigungen pro Entität
└── Services/        → Verfügbarkeit, Clustering, Billing

resources/js/
├── Pages/           → Inertia-Seiten (spiegeln Routen)
├── Layouts/         → AuthenticatedLayout, GuestLayout
└── components/      → UI + Domain-Komponenten

database/
├── migrations/      → Schema (22+ Migrationen)
├── seeders/         → Rollen, Pläne, Demo-Daten
└── factories/       → Test-/Seed-Daten

routes/
├── web.php          → Haupt-Routen
└── auth.php         → Login, Register, Passwort

docs/
└── STATUS.md        → Diese Datei (Projektgedächtnis)
```

**Mandantenfähigkeit:** Jede Firma (`Company`) ist ein Tenant. Kunden, Mitarbeiter, Termine usw. sind über `company_id` und `BelongsToCompany`-Trait + `CompanyScope` getrennt.

---

## Rollen & Zugriff

| Rolle | Wer | Kann |
|-------|-----|------|
| **super_admin** | Plattform-Betreiber / Entwickler | Firmen, Abos, Gutscheine, Billing-Einstellungen; Horizon; umgeht Policy- und Abo-Checks |
| **company_admin** | Firmen-Chef | Kunden, Services, Mitarbeiter, Terminplanung, Abo buchen, Kalender |
| **staff** | Techniker / Mitarbeiter | Kunden & Termine ansehen, eigene Arbeitszeiten, Kalender, wiederkehrende Services |

**Demo-Zugänge** (Passwort: `password`):

| Rolle | E-Mail |
|-------|--------|
| Super-Admin | `super@appointment.test` |
| Firmen-Admin | `admin@demo-wartung.test` |
| Mitarbeiter | `max.techniker@demo-wartung.test` |

Demo-Firmen (`demo-wartung`, `tech-service-nord`, `sued-service`) sind dauerhaft von der Abrechnung befreit (`billing_exempt = true`).

**Hinweis:** Öffentliche Registrierung erstellt User ohne `company_id` — Firmen werden vom Super-Admin angelegt.

---

## Kernfunktionen (Terminplanung)

```
Wiederkehrende Services → (manuell oder Cron 06:00) → PLZ-Clustering
  → Grok KI → 3 Terminvorschläge → E-Mail → Öffentliche Annahme/Ablehnung
  → bei Ablehnung: Verhandlung (max. 2 KI-Runden) → ggf. Eskalation
  → bei Annahme: Termin bestätigt
```

| Bereich | Fähigkeiten | Wichtige Pfade |
|---------|-------------|----------------|
| **Kunden** | CRUD, Adresse/PLZ, Suche & Filter | `CustomerController`, `Pages/Customers/Index.tsx` |
| **Service-Typen** | Wartungsarten mit Dauer | `ServiceTypeController`, `Pages/ServiceTypes/Index.tsx` |
| **Wiederkehrende Services** | Kunde ↔ Service, `next_due_at` | `CustomerRecurringServiceController`, `RecurringService` |
| **Mitarbeiter** | Qualifikationen, Puffer, Wochenverfügbarkeit inkl. Pausen | `StaffMemberController`, `Pages/Staff/Index.tsx` |
| **Arbeitszeiten** | 7-Tage-Plan für Staff | `StaffWorkingHoursController`, `Pages/Staff/WorkingHours.tsx` |
| **Termine** | proposed, confirmed, negotiation, completed, cancelled | `AppointmentController`, `Pages/Appointments/Index.tsx` |
| **KI-Planung** | Grok: Mitarbeiter + 3 Slots; Fallback ohne API-Key | `app/AI/GrokSchedulerService.php` |
| **Verhandlung** | Kundenfeedback per Token; max. 2 Runden | `Public/NegotiationController` |
| **Kalender** | Persönliche Terminübersicht | `StaffCalendarController`, `Pages/Staff/Calendar.tsx` |
| **Dashboard** | Fällige Services, Verhandlungen, heutige Termine | `DashboardController`, `Pages/Dashboard.tsx` |

**Datenschutz bei KI:** An Grok gehen nur IDs, PLZ, Dauer und Verfügbarkeiten — keine Namen oder vollständigen Adressen.

**CLI:** `php artisan appointments:schedule-due` — täglich 06:00 via `routes/console.php`.

---

## Kundensuche (KI-Prospects)

Branch `customersearch`. Potenzielle B2B-Kunden per Google Places + Grok-Bewertung.

| Funktion | Beschreibung |
|----------|--------------|
| **Datenquelle** | Wählbar pro Suchprofil: **Google Places API** oder **Apify** (`compass/crawler-google-places`) |
| **KI** | Grok bewertet Treffer nach Branchen & Sonderhinweisen (`GrokProspectSearchService`) |
| **Dedup** | Bestehende Kunden per `place_id`, Name, Telefon, E-Mail, PLZ+Stadt ausschließen |
| **Speicherung** | Separate Tabelle `customer_prospects` — zählt nicht gegen Kunden-Limit bis `convert` |
| **Zugriff** | `Company::hasProspectSearchAccess()` = `billing_exempt` ODER Override ODER Plan-Feature ODER Stripe-Add-on |
| **Billing** | `plans.includes_prospect_search`, Add-on-Preis in `billing_settings`, `prospect_search_override` pro Firma |
| **Outreach** | Kaltakquise-E-Mail mit Opt-out (`/p/prospects/opt-out/{token}`) |
| **Feedback** | `prospect_feedback` + Grok Collections RAG (`documents/search`, hybrid) pro Firma |
| **Scheduler** | `prospects:run-scheduled` täglich 07:00; `prospects:purge-old` wöchentlich (DSGVO) |

**Env:** `GOOGLE_PLACES_API_KEY`, `APIFY_TOKEN`, `APIFY_PROSPECT_ACTOR_ID`, `PROSPECT_MAX_RESULTS_CAP`, `PROSPECT_RETENTION_DAYS`, `PROSPECT_OUTREACH_RATE_LIMIT_PER_DAY`, `XAI_API_KEY`, `XAI_MANAGEMENT_API_KEY`.

**Routen (company_admin):**

| Pfad | Zweck |
|------|-------|
| `/prospects` | Dashboard, Suchprofile, Prospect-Liste, Upsell |
| `/billing/prospect-addon` | Add-on buchen (POST) |
| `/p/prospects/opt-out/{token}` | Öffentlicher Opt-out |

**Wichtige Dateien:**

- `app/Services/Prospect/` — Geocoding, Google Places, Dedup, Orchestrator, Feedback
- `app/AI/GrokProspectSearchService.php`
- `app/Jobs/SearchCustomerProspectsJob.php`, `SendProspectOutreachJob.php`
- `app/Http/Controllers/ProspectHubController.php`, `ProspectSearchProfileController.php`, `CustomerProspectController.php`
- `resources/js/Pages/Prospects/Index.tsx`

---

## Billing & Abos (Stripe)

Implementiert auf Branch `feature/stripe-billing`. Billable-Entity: `Company` (Laravel Cashier).

| Funktion | Beschreibung |
|----------|--------------|
| **Abos (Pläne)** | Super-Admin: Basispreis, inkl. Mitarbeiter/Kunden (auch unendlich), Preis je Überschreitung |
| **Testzeitraum** | Neue Firmen: 30 Tage Standard (`billing_settings.default_trial_days`), ohne Zahlungsmethode |
| **Read-only** | Ohne Abo/Trial: GET erlaubt, POST/PATCH/DELETE blockiert (`EnsureCompanySubscribed`) |
| **Überschreitung** | Automatisch auf nächste Rechnung; Warnung beim Anlegen (`BillingOverageWarning`) |
| **Gutscheine** | Stripe-Coupons + Promo-Codes; einlösbar im Checkout |
| **Firmen-Overrides** | Pro Firma: Limits, Trial, `billing_exempt` |
| **Company-Admin UI** | `/billing` — Nutzung, Abo wählen, Checkout, Portal, Rechnungen |

**Voller Zugriff** (`Company::hasFullAccess()`): `billing_exempt` ODER Generic Trial ODER aktives Stripe-Abo.

**Standard-Plan (Seed):** Basis — 29 €/Monat, 5 Mitarbeiter, 100 Kunden, 5 €/extra Mitarbeiter, 0,50 €/extra Kunde.

**Stripe-Env:** `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `CASHIER_CURRENCY=eur` — Webhook: `POST /stripe/webhook`.

**Wichtige Billing-Dateien:**

- `app/Models/Plan.php`, `app/Models/BillingSetting.php`
- `app/Services/Billing/PlanService.php`, `PlanLimitService.php`, `UsageSyncService.php`
- `app/Http/Middleware/EnsureCompanySubscribed.php`
- `app/Http/Controllers/Admin/PlanController.php`, `CouponController.php`, `BillingSettingsController.php`
- `app/Http/Controllers/Billing/SubscriptionController.php`
- `resources/js/Pages/Admin/Plans/Index.tsx`, `Admin/Coupons/Index.tsx`, `Billing/Index.tsx`

---

## Öffentliche Seiten (ohne Login)

| Route | Zweck |
|-------|--------|
| `/` | Welcome / Landing |
| `/p/proposals/{token}` | Kunde sieht 3 Terminvorschläge |
| `/p/proposals/{token}/accept` | Termin bestätigen |
| `/p/proposals/{token}/reject` | Ablehnen → Verhandlung |
| `/p/negotiations/{token}` | Feedback für neue Vorschläge |

---

## Hintergrund & Infrastruktur

| Komponente | Aufgabe |
|------------|---------|
| `ProcessSchedulingJob` | KI-Planung für eine Firma |
| `ProcessNegotiationJob` | Neue Vorschläge nach Kundenfeedback |
| `SendProposalEmailJob` | E-Mail mit Proposal-Link |
| `SyncCompanyUsageJob` | Stripe-Überschreitungsmengen synchronisieren |
| `appointments:schedule-due` | Täglich 06:00 — fällige Services planen |
| **Horizon** | `/horizon` — Queue-Monitoring (nur Super-Admin) |

**Lokal:** `docker compose up -d` (Postgres + Redis), `php artisan migrate --seed`, `npm run dev`.

---

## Routen-Übersicht

### Super-Admin (`role:super_admin`)

| Pfad | Seite |
|------|-------|
| `/admin/companies` | Firmen verwalten (Abo, Trial, Limits, Befreiung) |
| `/admin/plans` | Abos CRUD + Standard-Testzeitraum |
| `/admin/coupons` | Gutscheine & Promo-Codes |

### Firmen-Nutzer (`auth`, `company`, `subscribed`)

| Pfad | Seite | Rolle |
|------|-------|-------|
| `/dashboard` | Dashboard | alle |
| `/customers` | Kunden | admin + staff (Lesen/Services) |
| `/service-types` | Services | admin + staff |
| `/staff` | Mitarbeiter | nur admin |
| `/appointments` | Termine | alle |
| `/my-calendar` | Kalender | alle |
| `/working-hours` | Arbeitszeiten | staff |

### Firmen-Admin zusätzlich

| Pfad | Seite |
|------|-------|
| `/billing` | Abo & Abrechnung (ohne `subscribed`-Middleware) |
| `/prospects` | Kundensuche (Upsell ohne Feature; Schreibzugriff mit `prospect_search`-Middleware) |

---

## Tests

61 Feature-/Unit-Tests. Billing-relevant:

- `tests/Feature/BillingAccessTest.php` — Read-only, Trial, Billing-Seite
- `tests/Feature/PlanLimitServiceTest.php` — Limits, Overrides, Überschreitung
- `tests/Feature/PlanAdminTest.php` — Plan-CRUD, Company-Billing, Usage-Sync-Job
- `tests/Feature/ProspectSearchTest.php` — Zugriff, Profile, Dedup, Billing-Gate
- `tests/Feature/ProspectGrokAndOutreachTest.php` — Grok Collections, Outreach-Limit, Feedback-Sync

---

## Changelog (STATUS.md)

| Datum | Änderung |
|-------|----------|
| 2026-06-13 | Initiale STATUS.md: Struktur- & Fähigkeitsübersicht inkl. Stripe-Billing auf `feature/stripe-billing` |
| 2026-06-23 | `feature/stripe-billing` in `master` gemerged (Fast-forward) |
| 2026-06-23 | KI-Kundensuche auf Branch `customersearch`: Google Places, Grok-Scoring, Billing-Gate, Outreach, Opt-out |
| 2026-06-24 | Grok Collections RAG für Prospect-Feedback, Outreach-Tageslimit, Stripe Add-on Sync im Seeder |
| 2026-06-26 | Datenquellen-Switch: Google Places oder Apify pro Suchprofil |
