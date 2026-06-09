# Appointment Scheduler – KI-gestützte SaaS-Plattform

Multi-Tenant SaaS für Wartungs- und Terminplanung mit Routenoptimierung (PLZ-Clustering), Grok/xAI-gestützter Terminfindung und interaktivem Verhandlungsprozess per E-Mail.

## Tech-Stack

- **Laravel 12** + **Inertia.js v2** + **React 19** + **TypeScript**
- **Tailwind CSS v4** + **shadcn/ui**
- **PostgreSQL** · **Redis** · **Laravel Horizon**
- **grok-php/laravel** (xAI/Grok) · **spatie/laravel-permission**

## Voraussetzungen

- PHP 8.2+, Composer, Node.js 20+, npm
- PostgreSQL 16+ und Redis (oder via Docker Compose)

## Setup

```bash
# Infrastruktur starten (PostgreSQL + Redis)
docker compose up -d

# Dependencies
composer install
npm install
cp .env.example .env
php artisan key:generate

# .env anpassen (mindestens):
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_DATABASE=appointment_scheduler
# DB_USERNAME=postgres
# DB_PASSWORD=secret
# QUEUE_CONNECTION=redis
# CACHE_STORE=redis
# SESSION_DRIVER=redis
# XAI_API_KEY=your-xai-api-key   # optional – Fallback ohne API-Key

php artisan migrate --seed
npm run build
```

## Entwicklung starten

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev

# Terminal 3 – Queue Worker
php artisan queue:work
# oder mit Horizon (Super-Admin): php artisan horizon
```

## Demo-Zugänge (nach `migrate --seed`)

| Rolle | E-Mail | Passwort |
|-------|--------|----------|
| Super Admin | `super@appointment.test` | `password` |
| Company Admin (Demo Wartung) | `admin@demo-wartung.test` | `password` |
| Staff (Demo Wartung) | `max.techniker@demo-wartung.test` | `password` |

Weitere Firmen: `admin@techservice-nord.test`, `admin@sued-service.test`

## Demo-Flow

1. **Als Company Admin einloggen** → Dashboard zeigt fällige Wartungen
2. **Termine → „KI-Planung starten“** oder CLI:
   ```bash
   php artisan appointments:schedule-due
   php artisan queue:work --stop-when-empty
   ```
3. **E-Mails** landen im Log (`MAIL_MAILER=log`) – Proposal-Links aus `storage/logs/laravel.log` oder direkt aus der DB:
   ```bash
   php artisan tinker --execute="echo route('public.proposals.show', \App\Models\AppointmentProposal::first()->token);"
   ```
4. **Kunde öffnet Link** → 3 Optionen wählen oder ablehnen
5. **Bei Ablehnung** → Verhandlungsformular mit Terminwünschen → Grok generiert neue Vorschläge (Queue)
6. **Nach 2 Runden** → Eskalation mit WhatsApp-Zusammenfassung für manuellen Kontakt

## Architektur (Kurzüberblick)

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  Recurring  │────▶│ ClusteringService │────▶│ GrokScheduler   │
│  Services   │     │ (PLZ-Regionen)  │     │ (xAI API)       │
└─────────────┘     └──────────────────┘     └────────┬────────┘
                                                       │
┌─────────────┐     ┌──────────────────┐              ▼
│ Availability│◀────│ ProposalScheduling│◀─── 3 Slots / Kunde
│ Service     │     │ Service           │
└─────────────┘     └────────┬─────────┘
                             │
                    ┌────────▼────────┐
                    │ E-Mail + Public │
                    │ React Forms     │
                    └─────────────────┘
```

- **Multi-Tenancy:** `company_id` + Global Scope (`BelongsToCompany` Trait)
- **Rollen:** `super_admin`, `company_admin`, `staff` (Spatie Permission)
- **KI-Datenschutz:** Nur IDs, PLZ, Dauer, Slots – keine Namen/Adressen an Grok
- **API-ready:** Getrennte Services/DTOs, TypeScript-Typen in `resources/js/types/`

## Wichtige Artisan-Befehle

```bash
php artisan appointments:schedule-due          # alle Firmen
php artisan appointments:schedule-due --company=1
php artisan test
php artisan horizon                            # Queue-Dashboard (Super Admin)
```

## Umgebungsvariablen

| Variable | Beschreibung |
|----------|--------------|
| `XAI_API_KEY` | xAI API Key (wird als `GROK_API_KEY` verwendet) |
| `GROK_DEFAULT_MODEL` | z.B. `grok-2-latest` |
| `QUEUE_CONNECTION` | `redis` (empfohlen) |
| `MAIL_MAILER` | `log` (Dev) oder `resend`/`mailgun` (Prod) |

## Tests

```bash
php artisan test
```

## Lizenz

MIT
