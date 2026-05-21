# GEMINI.md — PropTrack

> **For AI agents inheriting this project:** Read this file completely before writing a single line of code. Then read `AGENT.md`. Then read `PropTrack-Implementation-Plan.md`. Only then start coding.

---

## 1. What Is PropTrack?

PropTrack is a **production-quality property management platform** built as a real-world teaching project. It serves three user types:

- **Owners** — manage properties, review contracts and financials
- **Agents** — handle tenant requests and support tickets
- **Tenants** — pay rent, view contracts, submit complaints

### Core Feature Set (Full Vision)

| Feature | Status |
|---|---|
| Authentication & roles | ✅ Complete |
| Property management (CRUD, photos, map) | ✅ Complete |
| Tenant management | ✅ Complete |
| Rental contracts (create, terminate, PDF) | ✅ Complete |
| Invoice & billing | ✅ Complete |
| Payment gateway (Midtrans/Xendit) | ✅ Complete |
| Financial reports | ✅ Complete |
| Complaint ticketing | ✅ Complete |
| Notifications (email + WhatsApp) | ✅ Complete |
| Real-time events (Reverb/WebSocket) | ✅ Complete |

---

## 2. Architecture — Non-Negotiable

This is a **fully decoupled** system. Do not change this.

```
proptrack/
├── proptrack-api/    ← Laravel 12 REST API (PHP 8.4)
└── proptrack-web/    ← Vue 3 SPA (Vite + TypeScript)
```

**Communication:** HTTP/JSON only. No sessions. No Inertia. No SSR. No cookie auth.

**Auth:** Laravel Sanctum Bearer tokens. Token stored in Pinia store + `localStorage`. Axios attaches it automatically via the global instance in `src/plugins/axios.ts`.

---

## 3. Tech Stack

### Backend (`proptrack-api`)

| Package | Purpose |
|---|---|
| Laravel 12 / PHP 8.4 | API framework |
| Laravel Sanctum | Token auth |
| Spatie Permission | Roles: `admin`, `owner`, `agent`, `tenant` |
| Spatie Media Library | Photo uploads (properties) |
| **Spatie Laravel PDF v2.8.0** | PDF generation (contracts, invoices, reports) |
| Laravel Reverb | WebSocket server (Phase 4) |
| Redis | Cache + queue driver |
| Laravel Horizon | Queue monitoring |
| **Pest PHP v3** | Testing framework |

### Frontend (`proptrack-web`)

| Package | Purpose |
|---|---|
| Vue 3 + Composition API | SPA |
| Vue Router 4 | Routing |
| Pinia | State management |
| Axios | API client |
| Vanilla CSS (custom vars) | Styling — **NOT Tailwind in practice** |
| Vite 6 | Build tool |
| TypeScript (strict) | Type safety |
| Vue Leaflet | Maps on property detail |

> ⚠️ `AGENT.md` lists Tailwind CSS v4 but the actual implementation uses **Vanilla CSS with CSS custom properties** defined in `src/index.css`. Do not introduce Tailwind.

---

## 4. What Has Been Built

### Phase 1 — Foundation ✅

**1.1 Project Setup**
- Laravel API at `proptrack-api/`, Vue SPA at `proptrack-web/`
- Global Axios instance (`src/plugins/axios.ts`) with Bearer token injection and 401/422 handling
- Pinia auth store (`src/stores/auth.ts`)
- Role seeder: `admin`, `owner`, `agent`, `tenant`

**1.2 Authentication**
- `POST /api/v1/auth/register` — assigns role, returns token
- `POST /api/v1/auth/login` — returns Sanctum Bearer token + user with roles
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- Frontend: `LoginPage.vue`, `RegisterPage.vue`, `useAuth.ts` composable
- Route guards on `router/index.ts` — redirects unauthenticated users to `/login`

---

### Phase 2 — Core Domain ✅

**2.1 Property Management**

Backend files:
- `database/migrations/..._create_properties_table.php` — UUID PK, `owner_id`, type, status, lat/lng, monthly_price
- `app/Models/Property.php` — HasUuids, HasFactory, InteractsWithMedia
- `app/Policies/PropertyPolicy.php` — owner can CRUD own properties; admin can all
- `app/Http/Requests/StorePropertyRequest.php` / `UpdatePropertyRequest.php`
- `app/Http/Resources/PropertyResource.php`
- `app/Services/PropertyService.php`
- `app/Http/Controllers/Api/PropertyController.php` — thin, delegates to service
- `tests/Feature/PropertyTest.php` — 30 tests

Frontend files:
- `src/types/property.ts`
- `src/services/propertyService.ts`
- `src/stores/property.ts`
- `src/composables/useProperty.ts`
- `src/pages/properties/PropertyListPage.vue` — card grid, search, filters, pagination
- `src/pages/properties/PropertyDetailPage.vue` — Leaflet map, photo gallery, media upload
- `src/pages/properties/PropertyFormPage.vue` — full CRUD form

Routes: `GET/POST/PUT/DELETE /api/v1/properties`, `POST /api/v1/properties/{id}/photos`, `DELETE /api/v1/properties/{id}/photos/{mediaId}`

---

**2.2 Tenant Management**

Backend files:
- `database/migrations/..._create_tenants_table.php` — UUID PK, `id_card_number varchar(16)`, email unique
- `app/Models/Tenant.php` — HasUuids, `activeContract()` HasOne → Contract where status=active
- `app/Policies/TenantPolicy.php` — owner/admin CRUD; delete=admin only
- `app/Http/Requests/StoreTenantRequest.php` — `digits:16` for KTP
- `app/Http/Requests/UpdateTenantRequest.php` — `Rule::unique()->ignore()` for own email
- `app/Http/Resources/TenantResource.php` — returns live `active_contract` data
- `app/Services/TenantService.php`
- `app/Http/Controllers/Api/TenantController.php`
- `tests/Feature/TenantTest.php` — 19 tests

Frontend files:
- `src/types/tenant.ts`
- `src/services/tenantService.ts`
- `src/stores/tenant.ts`
- `src/composables/useTenant.ts`
- `src/pages/tenants/TenantListPage.vue` — table layout, debounced search, avatar initials, contract badge
- `src/pages/tenants/TenantDetailPage.vue` — info cards, active contract section
- `src/pages/tenants/TenantFormPage.vue` — monospace KTP input with 16/16 live counter

Routes: `GET/POST/PUT/DELETE /api/v1/tenants`

---

**2.3 Kontrak Sewa (Rental Contracts)**

Backend files:
- `database/migrations/..._create_contracts_table.php` — UUID PK, FK to tenants + properties, status enum (`active`/`expired`/`terminated`), `terminated_at`
- `app/Models/Contract.php` — HasUuids, `isActive()` helper
- `app/Policies/ContractPolicy.php` — terminate=owner/admin; delete=admin only
- `app/Http/Requests/StoreContractRequest.php` — validates dates, billing_date 1-28
- `app/Http/Resources/ContractResource.php`
- `app/Services/ContractService.php` — key guard: `assertNoActiveContractForProperty()` throws 422 if property already has active contract
- `app/Http/Controllers/Api/ContractController.php` — CRUD + terminate + document download
- `app/Jobs/GenerateContractPdfJob.php` — `ShouldQueue`, 3 retries, 10s backoff
- `resources/views/pdf/contract.blade.php` — bilingual Bahasa Indonesia PDF with IDR formatting, signature block, status badge
- `tests/Feature/ContractTest.php` — 15 tests

Frontend files:
- `src/types/contract.ts`
- `src/services/contractService.ts` — `downloadDocument()` fetches blob, triggers browser download
- `src/stores/contract.ts`
- `src/composables/useContract.ts`
- `src/components/tenant/ContractCard.vue` — tenant→property display, colored status badge (green/gray/red), terminate + PDF buttons
- `src/pages/contracts/ContractListPage.vue` — status tab filter (All/Active/Expired/Terminated)
- `src/pages/contracts/ContractDetailPage.vue` — clickable tenant/property cross-links
- `src/pages/contracts/ContractFormPage.vue` — tenant + property dropdowns loaded in parallel

Routes:
```
GET/POST       /api/v1/contracts
GET/PUT        /api/v1/contracts/{id}
POST           /api/v1/contracts/{id}/terminate
GET            /api/v1/contracts/{id}/document     ← PDF binary download
```

---

### Phase 3 — Billing, Payments & Reports ✅

**3.1 Invoice & Billing**

Backend files:
- `database/migrations/..._create_invoices_table.php` — UUID PK, FK to contracts, tenants, properties. Fields: `invoice_number`, `status` (unpaid, paid, overdue), `amount`, `billing_month` (YYYY-MM), `due_date`, `paid_at`, `payment_gateway`
- `app/Models/Invoice.php` — HasUuids, belongsTo relationships, helper methods
- `app/Policies/InvoicePolicy.php` — Owner can view owned invoices, tenant can view their own, admin can do all
- `app/Http/Requests/StoreInvoiceRequest.php` / `UpdateInvoiceRequest.php` (if any)
- `app/Http/Controllers/Api/InvoiceController.php` — index, show, send (manual trigger), document
- `app/Services/InvoiceService.php` — Handles automatic monthly billing via scheduler or contract creation
- `resources/views/pdf/invoice.blade.php` — Bilingual elegant invoice layout
- `tests/Feature/InvoiceTest.php` — 16 tests

Frontend files:
- `src/types/invoice.ts`
- `src/services/invoiceService.ts`
- `src/composables/useInvoice.ts`
- `src/pages/invoices/InvoiceListPage.vue` — Modern invoices list with responsive tables, filters, status badges
- `src/pages/invoices/InvoiceDetailPage.vue` — Visual details with glassmorphism cards, payment status, and export PDF button

Routes:
- `GET /api/v1/invoices`
- `GET /api/v1/invoices/{id}`
- `POST /api/v1/invoices/{id}/send`
- `GET /api/v1/invoices/{id}/document`

---

**3.2 Payment Gateway (Midtrans/Xendit)**

Backend files:
- `app/Http/Controllers/Api/PaymentController.php` — createTransaction, status, webhooks
- `app/Services/PaymentService.php` — Integrates with Midtrans Snap API and handles signature verification on webhooks
- `app/Jobs/HandleMidtransWebhookJob.php` — Processes incoming webhook asynchronously
- `tests/Feature/PaymentTest.php` — 16 tests

Frontend files:
- `src/types/payment.ts`
- `src/services/paymentService.ts`
- `src/pages/payments/PaymentPage.vue` — Renders invoice details and embeds the Midtrans Snap payment modal
- `src/pages/payments/PaymentStatus.vue` — Success, pending, or failure confirmation with animated states

Routes:
- `POST /api/v1/payments/create-transaction`
- `GET /api/v1/payments/{invoice}/status`
- `POST /api/v1/payments/webhook/midtrans` (public webhook)

---

**3.3 Laporan Keuangan (Financial Reports)**

Backend files:
- `app/Services/ReportService.php` — Aggregations, owner scoping, collection rate logic, Spatie PDF rendering
- `app/Http/Controllers/Api/ReportController.php` — financial (overall), propertyFinancial (single property), export (PDF binary download)
- `app/Jobs/GenerateFinancialReportJob.php` — Queues PDF generation
- `resources/views/pdf/financial-report.blade.php` — Bilingual PDF template with metrics cards and property lists
- `tests/Feature/ReportTest.php` — 8 tests

Frontend files:
- `src/types/report.ts`
- `src/services/reportService.ts`
- `src/composables/useReport.ts`
- `src/pages/reports/FinancialReportPage.vue` — Modern dashboard with interactive 12-month bar chart (vue-chartjs), year/month filters, gradient metrics, and CSV/PDF export options

Routes:
- `GET /api/v1/reports/financial`
- `GET /api/v1/reports/financial/{property}`
- `GET /api/v1/reports/financial/export`

---

### Phase 4 — Communication & Real-time ✅

**4.1 Sistem Tiket Keluhan (Complaint Ticket System)**

Backend files:
- `database/migrations/..._create_tickets_table.php` — UUID PK, FK to tenants, properties, and users (submitter/agent). Fields: `ticket_number` (TKT-YYYY-XXXX), `status` (open, in_progress, resolved, closed), `priority`, `category`, `title`, `description`
- `database/migrations/..._create_ticket_comments_table.php` — UUID PK, FK to tickets and users.
- `app/Models/Ticket.php` — UUID model with automatic sequential locking query logic for thread-safe number generation
- `app/Models/TicketComment.php` — UUID model for comments
- `app/Policies/TicketPolicy.php` — Restricts access: Tenant only views/creates own tickets, Owner only views tickets for owned properties, Agent/Admin manages all
- `app/Http/Requests/StoreTicketRequest.php` / `UpdateTicketStatusRequest.php` / `StoreTicketCommentRequest.php`
- `app/Http/Resources/TicketResource.php` / `TicketCommentResource.php`
- `app/Services/TicketService.php` — Business logic for thread-safe generation, active contract validations, status changes, and comments
- `app/Http/Controllers/Api/TicketController.php` — Thin handlers delegating to `TicketService`
- `tests/Feature/TicketTest.php` — 9 feature tests covering complete RBAC permissions and transitions

Frontend files:
- `src/types/ticket.ts` — Structured Ticket and TicketComment interfaces
- `src/services/ticketService.ts` — Typed Axios wrapper for ticket actions
- `src/stores/ticket.ts` — Pinia state management for ticketing
- `src/composables/useTicket.ts` — Composition coordinator linking services, stores, and components
- `src/pages/tickets/TicketListPage.vue` — Premium search & filters list using harmonious HSL vars
- `src/pages/tickets/TicketFormPage.vue` — Submission form matching tenants to their active contracted properties
- `src/pages/tickets/TicketDetailPage.vue` — Double-column view containing assignment panel and chronologically sorted, role-colored comment thread

Routes:
- `GET /api/v1/tickets`
- `POST /api/v1/tickets`
- `GET /api/v1/tickets/{id}`
- `PUT /api/v1/tickets/{id}/status`
- `POST /api/v1/tickets/{id}/comments`

---

**4.2 Sistem Notifikasi (Notification System)**

Backend files:
- `database/migrations/..._create_notifications_table.php` — standard Laravel database notifications table
- `app/Notifications/NewTicketNotification.php` — tenant submitted ticket notification (dispatched to owners/agents)
- `app/Notifications/TicketStatusChangedNotification.php` — ticket status transitions notification (dispatched to tenant)
- `app/Notifications/InvoiceCreatedNotification.php` — monthly invoice creation notification (dispatched to tenant)
- `app/Notifications/InvoiceDueNotification.php` — invoice due soon warning notification (dispatched to tenant)
- `app/Notifications/PaymentConfirmedNotification.php` — invoice payment confirmation notification (dispatched to tenant)
- `app/Http/Controllers/Api/NotificationController.php` — handles listing and marking as read
- `app/Http/Resources/NotificationResource.php` — JSON resource mapping structured database notification payload
- `tests/Feature/NotificationTest.php` — 8 tests covering DB/Fonnte API and queue triggers

Frontend files:
- `src/types/notification.ts` — type structure for notifications
- `src/services/notificationService.ts` — HTTP wrapper calling index and read endpoints
- `src/stores/notification.ts` — Pinia store holding notifications feed state and unread badge count
- `src/composables/useNotification.ts` — fetches notifications, updates store, and triggers router navigation
- `src/components/layout/NavbarNotificationBell.vue` — modern dropdown component presenting feed list, unread badge, and "Mark all read" controls

Routes:
- `GET /api/v1/notifications` — list authenticated user notifications
- `POST /api/v1/notifications/{id}/read` — mark single notification as read
- `POST /api/v1/notifications/read-all` — mark all notifications as read

---

**4.3 Real-time dengan Laravel Reverb**

Backend files:
- `config/reverb.php` — first-party WebSocket server settings
- `config/broadcasting.php` — Reverb broadcasting driver setup
- `routes/channels.php` — Sanctum-guarded private channel authorization rules
- `app/Events/NotificationSent.php` — broadcasts formatted notification payload on `private-user.{userId}`
- `app/Events/TicketStatusUpdated.php` — broadcasts status changes on `private-ticket.{ticketId}`
- `app/Events/PaymentConfirmed.php` — broadcasts payment confirmations on `private-invoice.{invoiceId}`
- `app/Listeners/SendRealtimeNotification.php` — intercepts database notification event and dispatches real-time broadcast event
- `tests/Feature/BroadcastingTest.php` — 6 comprehensive RBAC private channel authorization and event dispatch feature tests

Frontend files:
- `src/composables/useEcho.ts` — manages singleton Echo instance initialized with `VITE_REVERB_*` environmental variables, injecting the active Sanctum Bearer token into auth headers
- `src/components/layout/NavbarNotificationBell.vue` — subscribes to `private-user.{userId}` channel to prepend new notifications and bump unread count badge in real-time
- `src/pages/tickets/TicketDetailPage.vue` — subscribes to `private-ticket.{ticketId}` to update ticket and comment status reactively on changes
- `src/pages/payments/PaymentPage.vue` — subscribes to `private-invoice.{invoiceId}` to capture immediate webhooks and transition checkout straight to success

---

### Test Status

```
php artisan test → 127/127 passed (30 property + 19 tenant + 15 contract + 16 invoice + 16 payment + 8 report + 9 ticket + 8 notification + 6 broadcasting)
npm run build   → 268 modules, 0 errors, 0 TypeScript errors
```

---

## 5. Critical Patterns — Follow These Exactly

### Backend

```
Controller → FormRequest (authorize + validate) → Service → Resource → JSON
```

- **Controllers**: HTTP only. No DB queries. No business logic.
- **Services**: All business logic. Throw `ValidationException` for domain rule violations.
- **Resources**: Always use `JsonResource`. Never return raw arrays.
- **Policies**: Authorization lives here. Backend is the source of truth.
- **Jobs**: PDF generation, notifications, webhooks — all queued, never synchronous.

### Frontend

```
Page → Composable → Service → API
               ↕
             Store (Pinia)
```

- **Services** (`src/services/`): Typed Axios calls, nothing else.
- **Stores** (`src/stores/`): State + mutations only. No API calls. No business logic.
- **Composables** (`src/composables/`): Wire service → store → router. Pages call composables, not services/stores directly.
- **Pages** (`src/pages/`): Mount, call composable, render template. No direct API calls.

### API Response Shape (always)

```json
{ "data": {}, "meta": {}, "message": "Success" }
```

Paginated lists:
```json
{ "data": [...], "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }, "message": "Success" }
```

---

## 6. Key Business Rules

1. **One active contract per property** — `ContractService::assertNoActiveContractForProperty()` enforces this. A property can get a new contract after its previous one is terminated.
2. **KTP = 16 digits, numeric** — validated at both API (`digits:16`) and frontend (regex + live counter).
3. **PDF generation is always async** — `GenerateContractPdfJob` is dispatched on contract create. Synchronous PDF is generated only for direct `/document` downloads.
4. **Role hierarchy**: `admin` > `owner` > `agent` > `tenant`. Tenant role users cannot create/manage properties, tenants, or contracts.
5. **Auth**: Bearer token stored in `localStorage` key `proptrack_token`. Never use cookies or sessions.

---

## 7. File Reference Map

### Backend key files

| File | Purpose |
|---|---|
| `routes/api.php` | All API routes |
| `app/Http/Controllers/Api/` | Thin HTTP handlers |
| `app/Services/` | Business logic |
| `app/Models/` | Eloquent models (all use HasUuids) |
| `app/Policies/` | Authorization |
| `app/Http/Requests/` | Validation + authorization |
| `app/Http/Resources/` | JSON formatting |
| `app/Jobs/` | Queued background work |
| `resources/views/pdf/` | Blade templates for PDF |
| `tests/Feature/` | Pest feature tests |

### Frontend key files

| File | Purpose |
|---|---|
| `src/plugins/axios.ts` | Global Axios with Bearer token |
| `src/router/index.ts` | All SPA routes + auth guard |
| `src/stores/auth.ts` | Auth state (user, token, roles) |
| `src/types/` | TypeScript interfaces for all domain models |
| `src/services/` | Typed API wrappers |
| `src/stores/` | Pinia state (property, tenant, contract) |
| `src/composables/` | Feature logic composables |
| `src/pages/` | Route-level page components |
| `src/components/` | Reusable UI components |

---

## 8. What's Next (Phase 4.3)

The next feature is **Phase 4.3 — Real-time dengan Laravel Reverb**. From `PropTrack-Implementation-Plan.md`:

- Install and configure Laravel Reverb
- Event classes: `NotificationSent`, `TicketStatusUpdated`, `PaymentConfirmed`
- Broadcast each event to the correct channels
- Install `laravel-echo` and `pusher-js` in `proptrack-web`
- Create `composables/useEcho.ts` and handle real-time notification/payment updates

See `PropTrack-Implementation-Plan.md` lines 686–719 for full details.

---

## 9. How To Run Locally

```bash
# Backend
cd proptrack-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve            # → http://localhost:8000

# Queue worker (for jobs/notifications)
php artisan queue:work

# Frontend
cd proptrack-web
npm install
cp .env.example .env         # set VITE_API_URL=http://localhost:8000
npm run dev                  # → http://localhost:5173
```

---

## 10. Instructions for the Next Agent

1. **Read `AGENT.md` completely** before touching any code.
2. **Read `PropTrack-Implementation-Plan.md`** to understand which phase comes next.
3. **Never skip the pattern**: API contract → backend → tests pass → frontend → build passes.
4. **Run `php artisan test` before and after every backend change.** All 64 tests must remain green.
5. **Run `npm run build` before declaring frontend work complete.** Zero TypeScript errors required.
6. **Do not use Inertia, SSR, sessions, or cookie auth.** The architecture is a hard constraint.
7. **Do not add packages without explaining why and asking first.**
8. **Test helper functions in Pest must have unique names** across all test files — prefix them with the feature name (e.g., `contractMakeTenant()`) to avoid PHP fatal redeclaration errors.
9. **`TenantResource` now returns live `active_contract` data** — this was wired up in Phase 2.3. Do not revert it.
10. **Keep controllers thin** — if you find yourself writing a DB query in a controller, stop and move it to a Service.
