# 🏢 PropTrack — Enterprise Property Management Platform

PropTrack is a **production-quality, fully decoupled property management platform** built using modern technologies. It provides a seamless interface and real-time experience for three distinct types of users:

*   **Owners & Admins** — Oversee the properties portfolio, draft rental contracts, manage billing invoices, track platform financials, and assign support agents.
*   **Support Agents** — Manage resident maintenance and complaint ticket boards, update ticket states, and reply in thread chats.
*   **Tenants** — Review active lease details, download official bilingual PDF contracts, settle monthly rent securely online, and submit complaint tickets.

---

## 🏗️ Decoupled Architecture

PropTrack is engineered as a strictly decoupled client-server application. All communication occurs via structured HTTP/JSON APIs secured by Laravel Sanctum Bearer tokens.

```
proptrack/
├── proptrack-api/    ← RESTful API (Laravel 12, PHP 8.4)
└── proptrack-web/    ← Single Page Application (Vue 3, Vite, TypeScript)
```

### Technical Stack & Key Packages

#### Backend (`proptrack-api`)
*   **Core**: Laravel 12 / PHP 8.4 REST API
*   **Authentication**: Laravel Sanctum (token-based bearer auth)
*   **RBAC**: Spatie Permission (`admin`, `owner`, `agent`, `tenant`)
*   **Media**: Spatie Media Library (photo uploads for properties)
*   **PDF Generation**: Spatie Laravel PDF v2.8.0 (contracts, invoices, reports)
*   **Real-time & WebSockets**: Laravel Reverb + Redis cache & queue driver
*   **Monitoring**: Laravel Horizon queue manager
*   **Testing**: Pest PHP v3

#### Frontend (`proptrack-web`)
*   **Core**: Vue 3 (Composition API) + Router 4 + Pinia (State Management)
*   **Styling**: Modern Vanilla CSS with HSL CSS Custom Properties
*   **Maps**: Vue Leaflet (interactive maps on property detail pages)
*   **Build Tool**: Vite 6 + TypeScript (Strict Mode)
*   **API Client**: Axios (configured with global bearer token injection and auto-handling of `401 Unauthorized` / `422 Unprocessable Entity`)

---

## 🌟 Feature Checklist

- [x] **Secure Authentication**: Pinia + localStorage token persistence, auto-refresh profiles, and page router guards.
- [x] **Properties Portfolio**: Full CRUD, coordinates mapping with interactive Leaflet maps (geocoding + reverse geocoding), and photo gallery uploads.
- [x] **Tenant Directory**: Complete resident database with Indonesian 16-digit KTP verification (including live digit counters) and middle-digits KTP privacy masking.
- [x] **Rental Agreements**: Bilingual (Bahasa Indonesia & English) rental contracts. Enforces business rules (e.g., *one active contract per property*) and day dropdown constraints (1–28).
- [x] **Billing & Invoices**: Recurring monthly automated invoices with premium PDF exports and overdue navigation badges.
- [x] **Payment Gateway**: Seamless checkout integrating the **Midtrans Snap API modal** for real-time secure online transactions.
- [x] **Financial Analysis**: Interactive dashboards with a 12-month analytics bar chart, metric aggregations, and PDF exports.
- [x] **Complaint Ticketing**: Support resolution console allowing status transitions (`Open` $\rightarrow$ `In Progress` $\rightarrow$ `Resolved` $\rightarrow$ `Closed`), ticket claiming, and chronological thread comments.
- [x] **User Profile Settings**: Dedicated personal details and password change forms with local localized submission controls and standard secure show/hide eye toggles.
- [x] **Real-time WebSockets**: Laravel Reverb broadcasting events (`NotificationSent`, `TicketStatusUpdated`, `PaymentConfirmed`) directly to active users.
- [x] **Multichannel Notifications**: Real-time navbar bell alerts and database notifications.

---

## 🚀 Getting Started

### Prerequisites
*   PHP 8.4+ & Composer
*   Node.js 20+ & npm
*   SQLite / MySQL
*   Redis server (for queues & WebSockets)

### 1. Backend Setup (`proptrack-api`)
```bash
cd proptrack-api
composer install

# Environment Configuration
cp .env.example .env
php artisan key:generate

# Database Migration & Seeding
# This fresh migrates and seeds default roles, base accounts, and a high-fidelity real-world 
# dataset (properties, contracts, timelines, tickets, comments, and notifications) for immediate inspection:
# Accounts seeded:
# - admin@proptrack.com / owner@proptrack.com / agent@proptrack.com / tenant@proptrack.com (password: 'password')
# - tenant2@proptrack.com / tenant3@proptrack.com (password: 'password')
php artisan migrate:fresh --seed

# Start the Local Dev Server
php artisan serve # Serves at http://localhost:8000

# Start Queue Workers (for PDFs and notifications)
php artisan queue:work
```

### 2. Frontend Setup (`proptrack-web`)
```bash
cd proptrack-web
npm install

# Environment Configuration
cp .env.example .env
# Make sure VITE_API_URL is pointed to http://localhost:8000

# Start Vite Development Server
npm run dev # Serves at http://localhost:5173
```

---

## 🧪 Testing & Verification

PropTrack maintains rigorous standards for code correctness and visual excellence.

### Backend Unit & Feature Tests
Running the complete suite of backend tests:
```bash
cd proptrack-api
php artisan test
```
*Current coverage: **134/134 tests passing** (30 property tests, 19 tenant tests, 15 contract tests, 16 invoice tests, 16 payment tests, 8 report tests, 9 ticket tests, 8 notification tests, 6 broadcasting tests, and 7 authentication/profile update tests).*

### Frontend Production Builds
Validating type safety and bundle compiling:
```bash
cd proptrack-web
npm run build
```
*Vite compiles **successfully with 0 errors and 0 TypeScript warnings**.*

### E2E Visual & Integration Tests
Using automated Playwright simulations (`run-ui-tests.js`), we run tests covering:
1. **Property Owner** logs in, creates a property, registers a tenant, and establishes a contract.
2. **Tenant** logs in, views their personalized dashboard, and files a leaking AC ticket.
3. **Agent** claims the ticket, sets status to *In Progress*, and replies to the tenant.

Visual progress screenshots are captured and verified for premium quality inside the workspace.

---

## 🎨 Design System & Aesthetics
PropTrack stands out with a **highly customized, visual warm-cream light-theme interface**:
*   Curated HSL variables mapping depth (`#eaece7` outer backgrounds with crisp `#ffffff` card surfaces).
*   Dynamic branding highlights using amber variables (`var(--amber)`) for high-contrast action focus.
*   Modern typography utilizing the Google Font `Outfit` instead of system defaults.
*   Responsive layouts with glassmorphism overlays and smooth hover micro-animations to keep the dashboard interactive and alive.
