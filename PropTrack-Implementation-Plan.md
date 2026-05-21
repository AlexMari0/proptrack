# PropTrack — Implementation Plan

> Dokumen ini adalah panduan implementasi fitur-by-fitur untuk PropTrack.
> Setiap fase harus selesai dan terverifikasi sebelum melanjutkan ke fase berikutnya.
> Selalu baca `AGENT.md` sebelum mulai coding pada setiap fitur.

---

## Cara Membaca Dokumen Ini

Setiap fitur memiliki tiga bagian utama yang harus dikerjakan secara berurutan:

1. **API Contract** — definisikan dulu sebelum menulis kode apapun.
2. **Task List** — pekerjaan backend lalu frontend, tidak boleh dibalik.
3. **Kriteria Selesai (Definition of Done)** — fitur dianggap selesai hanya jika semua poin ini terpenuhi.

---

## FASE 1 — Fondasi

> Tujuan: Proyek bisa berjalan secara lokal, developer bisa login, dan struktur folder sudah benar.

---

### 1.1 Setup Project & Infrastructure

**Tidak ada API contract** — ini adalah pekerjaan konfigurasi.

**Task List — Backend:**
- [ ] `laravel new proptrack-api --no-interaction`
- [ ] Install packages: Sanctum, Spatie Permission, Spatie Media Library, Spatie PDF
- [ ] Konfigurasi `.env`: DB, Redis, Queue driver
- [ ] Setup struktur folder sesuai `AGENT.md` (`Services/`, `Policies/`, dst.)
- [ ] Buat seeder role: `admin`, `owner`, `agent`, `tenant`

**Task List — Frontend:**
- [ ] `npm create vue@latest proptrack-web -- --typescript`
- [ ] Install packages: Axios, Pinia, Vue Router, Tailwind CSS v4, VeeValidate, Zod
- [ ] Setup struktur folder sesuai `AGENT.md`
- [ ] Konfigurasi Axios global di `src/plugins/axios.ts` (base URL dari `VITE_API_URL`)
- [ ] Buat file `.env` dengan `VITE_API_URL=http://localhost:8000`

**Kriteria Selesai:**
- [ ] `php artisan serve` berjalan tanpa error
- [ ] `npm run dev` berjalan tanpa error
- [ ] Database terhubung, semua migrasi berhasil dijalankan
- [ ] Seeder berhasil: 4 role tersedia di tabel `roles`
- [ ] Axios terkonfigurasi dan `VITE_API_URL` terbaca dengan benar

---

### 1.2 Authentication

**API Contract:**

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout       ← requires Bearer token
GET    /api/v1/auth/me           ← requires Bearer token
```

**Register — Request:**
```json
{
  "name": "Budi Santoso",
  "email": "budi@example.com",
  "password": "password",
  "password_confirmation": "password",
  "role": "owner"
}
```

**Login — Request:**
```json
{
  "email": "budi@example.com",
  "password": "password"
}
```

**Login — Response `200`:**
```json
{
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": 1,
      "name": "Budi Santoso",
      "email": "budi@example.com",
      "roles": ["owner"]
    }
  },
  "message": "Login successful"
}
```

**Me — Response `200`:**
```json
{
  "data": {
    "id": 1,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "roles": ["owner"]
  },
  "message": "Success"
}
```

**Error `401`:**
```json
{
  "message": "Invalid credentials"
}
```

**Task List — Backend:**
- [ ] `AuthController` di `app/Http/Controllers/Api/`
- [ ] `LoginRequest`, `RegisterRequest` di `app/Http/Requests/`
- [ ] `UserResource` di `app/Http/Resources/`
- [ ] Route di `routes/api.php` dengan prefix `/api/v1/auth`
- [ ] Sanctum konfigurasi token mode

**Task List — Frontend:**
- [ ] `stores/auth.ts` — state: `user`, `token`, `isAuthenticated`
- [ ] `services/authService.ts` — fungsi `login()`, `logout()`, `me()`
- [ ] `pages/auth/LoginPage.vue` — form dengan VeeValidate + Zod
- [ ] `pages/auth/RegisterPage.vue`
- [ ] Axios request interceptor: attach `Authorization: Bearer {token}`
- [ ] Axios response interceptor: handle 401 → clear store → redirect login
- [ ] Route guard di `router/index.ts`

**Kriteria Selesai:**
- [ ] `POST /api/v1/auth/login` dengan kredensial valid → token dikembalikan
- [ ] `POST /api/v1/auth/login` dengan kredensial salah → `401`
- [ ] `GET /api/v1/auth/me` tanpa token → `401`
- [ ] `GET /api/v1/auth/me` dengan token valid → data user + roles
- [ ] Vue: login berhasil → token tersimpan di `localStorage` dan Pinia
- [ ] Vue: akses halaman protected tanpa login → redirect ke `/login`
- [ ] Vue: logout → token dihapus dari `localStorage` dan Pinia → redirect login

---

## FASE 2 — Core Domain

> Tujuan: Entitas utama bisnis (properti, tenant, kontrak) sudah bisa dikelola secara penuh.

---

### 2.1 Manajemen Properti

**API Contract:**

```
GET    /api/v1/properties            ← list dengan pagination & filter
POST   /api/v1/properties            ← buat properti baru
GET    /api/v1/properties/{id}       ← detail properti
PUT    /api/v1/properties/{id}       ← update properti
DELETE /api/v1/properties/{id}       ← hapus properti
POST   /api/v1/properties/{id}/photos  ← upload foto (multipart)
DELETE /api/v1/properties/{id}/photos/{mediaId}
```

**GET /api/v1/properties — Query Params:**
```
?page=1&per_page=15&status=available&type=kos&search=jakarta
```

**POST /api/v1/properties — Request:**
```json
{
  "name": "Kos Harmoni",
  "address": "Jl. Harmoni No. 12, Jakarta Pusat",
  "type": "kos",
  "status": "available",
  "latitude": -6.1751,
  "longitude": 106.8272,
  "description": "Kos strategis dekat MRT",
  "monthly_price": 1500000
}
```

**GET /api/v1/properties/{id} — Response `200`:**
```json
{
  "data": {
    "id": "uuid",
    "name": "Kos Harmoni",
    "address": "Jl. Harmoni No. 12, Jakarta Pusat",
    "type": "kos",
    "status": "available",
    "latitude": -6.1751,
    "longitude": 106.8272,
    "description": "Kos strategis dekat MRT",
    "monthly_price": 1500000,
    "owner": { "id": 1, "name": "Budi Santoso" },
    "photos": [
      { "id": 1, "url": "https://...", "thumbnail_url": "https://..." }
    ],
    "created_at": "2025-01-01T00:00:00Z"
  },
  "message": "Success"
}
```

**Task List — Backend:**
- [ ] Migration: `properties` table
- [ ] `Property` model dengan `HasMedia` dari Spatie
- [ ] `PropertyController` — CRUD + upload foto
- [ ] `StorePropertyRequest`, `UpdatePropertyRequest`
- [ ] `PropertyResource`, `PropertyCollection`
- [ ] `PropertyService` — logika bisnis (create, update, delete, upload foto)
- [ ] `PropertyPolicy` — hanya owner properti atau admin yang bisa edit/delete
- [ ] Register policy di `AuthServiceProvider`

**Task List — Frontend:**
- [ ] `types/property.ts` — interface `Property`, `PropertyListItem`
- [ ] `services/propertyService.ts` — fungsi API
- [ ] `stores/property.ts` — state: `properties`, `selectedProperty`
- [ ] `composables/useProperty.ts` — fetch, create, update, delete
- [ ] `pages/properties/PropertyListPage.vue` — tabel dengan filter dan pagination
- [ ] `pages/properties/PropertyDetailPage.vue` — detail + galeri foto + peta Leaflet
- [ ] `pages/properties/PropertyFormPage.vue` — form tambah/edit
- [ ] `components/property/PropertyCard.vue` — card reusable
- [ ] `components/property/PropertyGallery.vue` — galeri foto reusable

**Kriteria Selesai:**
- [ ] CRUD properti berfungsi penuh via Postman
- [ ] Upload foto berhasil → tersimpan di S3 (atau local disk di development)
- [ ] Owner hanya bisa edit/delete properti miliknya → `403` jika bukan miliknya
- [ ] Vue: list properti tampil dengan pagination
- [ ] Vue: filter berdasarkan status dan tipe berfungsi
- [ ] Vue: peta Leaflet menampilkan pin lokasi properti
- [ ] Vue: galeri foto tampil dan bisa di-scroll

---

### 2.2 Manajemen Tenant

**API Contract:**

```
GET    /api/v1/tenants
POST   /api/v1/tenants
GET    /api/v1/tenants/{id}
PUT    /api/v1/tenants/{id}
DELETE /api/v1/tenants/{id}
```

**POST /api/v1/tenants — Request:**
```json
{
  "name": "Ani Wijaya",
  "email": "ani@example.com",
  "phone": "081234567890",
  "id_card_number": "3171234567890001",
  "emergency_contact_name": "Ibu Sari",
  "emergency_contact_phone": "081298765432"
}
```

**GET /api/v1/tenants/{id} — Response `200`:**
```json
{
  "data": {
    "id": "uuid",
    "name": "Ani Wijaya",
    "email": "ani@example.com",
    "phone": "081234567890",
    "id_card_number": "3171234567890001",
    "emergency_contact_name": "Ibu Sari",
    "emergency_contact_phone": "081298765432",
    "active_contract": null,
    "created_at": "2025-01-01T00:00:00Z"
  },
  "message": "Success"
}
```

**Task List — Backend:**
- [ ] Migration: `tenants` table
- [ ] `Tenant` model
- [ ] `TenantController`, `TenantService`
- [ ] `StoreTenantRequest`, `UpdateTenantRequest`
- [ ] `TenantResource`, `TenantCollection`
- [ ] `TenantPolicy`

**Task List — Frontend:**
- [ ] `types/tenant.ts`
- [ ] `services/tenantService.ts`
- [ ] `stores/tenant.ts`
- [ ] `composables/useTenant.ts`
- [ ] `pages/tenants/TenantListPage.vue`
- [ ] `pages/tenants/TenantDetailPage.vue`
- [ ] `pages/tenants/TenantFormPage.vue`

**Kriteria Selesai:**
- [ ] CRUD tenant berfungsi penuh via Postman
- [ ] Vue: list tenant tampil dengan search berdasarkan nama/email
- [ ] Vue: form tenant dengan validasi KTP (16 digit angka)
- [ ] Vue: detail tenant menampilkan kontrak aktif (null jika belum ada)

---

### 2.3 Kontrak Sewa

**API Contract:**

```
GET    /api/v1/contracts
POST   /api/v1/contracts
GET    /api/v1/contracts/{id}
PUT    /api/v1/contracts/{id}
POST   /api/v1/contracts/{id}/terminate   ← akhiri kontrak sebelum waktunya
GET    /api/v1/contracts/{id}/document    ← download PDF kontrak
```

**POST /api/v1/contracts — Request:**
```json
{
  "tenant_id": "uuid",
  "property_id": "uuid",
  "start_date": "2025-02-01",
  "end_date": "2026-01-31",
  "monthly_rent": 1500000,
  "deposit_amount": 3000000,
  "billing_date": 1
}
```

**GET /api/v1/contracts/{id} — Response `200`:**
```json
{
  "data": {
    "id": "uuid",
    "status": "active",
    "tenant": { "id": "uuid", "name": "Ani Wijaya" },
    "property": { "id": "uuid", "name": "Kos Harmoni" },
    "start_date": "2025-02-01",
    "end_date": "2026-01-31",
    "monthly_rent": 1500000,
    "deposit_amount": 3000000,
    "billing_date": 1,
    "created_at": "2025-01-01T00:00:00Z"
  },
  "message": "Success"
}
```

**Task List — Backend:**
- [ ] Migration: `contracts` table
- [ ] `Contract` model dengan status: `active`, `expired`, `terminated`
- [ ] `ContractController`, `ContractService`
- [ ] `StoreContractRequest`
- [ ] `ContractResource`
- [ ] `ContractPolicy`
- [ ] Job `GenerateContractPdfJob` — generate PDF dengan Spatie PDF (queued)

**Task List — Frontend:**
- [ ] `types/contract.ts`
- [ ] `services/contractService.ts`
- [ ] `composables/useContract.ts`
- [ ] `pages/contracts/ContractListPage.vue`
- [ ] `pages/contracts/ContractDetailPage.vue`
- [ ] `pages/contracts/ContractFormPage.vue`
- [ ] `components/tenant/ContractCard.vue`

**Kriteria Selesai:**
- [ ] Kontrak baru bisa dibuat dengan menghubungkan tenant + properti
- [ ] Satu properti tidak bisa memiliki dua kontrak `active` secara bersamaan → validasi di `ContractService`
- [ ] `POST /api/v1/contracts/{id}/terminate` → status berubah menjadi `terminated`
- [ ] PDF kontrak bisa di-generate dan di-download
- [ ] Vue: form kontrak — tenant dan properti dipilih via dropdown
- [ ] Vue: status kontrak tampil dengan badge berwarna (active = hijau, expired = abu, terminated = merah)

---

## FASE 3 — Transaksi Keuangan

> Tujuan: Siklus pembayaran berjalan penuh — dari invoice terbuat otomatis hingga pembayaran terkonfirmasi.

---

### 3.1 Invoice & Billing

**API Contract:**

```
GET    /api/v1/invoices
GET    /api/v1/invoices/{id}
POST   /api/v1/invoices/{id}/send         ← kirim notifikasi ke tenant
GET    /api/v1/invoices/{id}/document     ← download PDF invoice
```

**GET /api/v1/invoices — Query Params:**
```
?status=unpaid&property_id=uuid&month=2025-02
```

**GET /api/v1/invoices/{id} — Response `200`:**
```json
{
  "data": {
    "id": "uuid",
    "invoice_number": "INV-2025-0001",
    "status": "unpaid",
    "contract": { "id": "uuid" },
    "tenant": { "id": "uuid", "name": "Ani Wijaya" },
    "property": { "id": "uuid", "name": "Kos Harmoni" },
    "amount": 1500000,
    "due_date": "2025-02-05",
    "billing_month": "2025-02",
    "paid_at": null,
    "created_at": "2025-02-01T00:00:00Z"
  },
  "message": "Success"
}
```

**Task List — Backend:**
- [ ] Migration: `invoices` table
- [ ] `Invoice` model dengan status: `unpaid`, `paid`, `overdue`, `cancelled`
- [ ] `InvoiceController`, `InvoiceService`
- [ ] `InvoiceResource`
- [ ] `GenerateMonthlyInvoicesCommand` — artisan command yang dijalankan via scheduler
- [ ] `GenerateInvoicePdfJob` — queued job

**Task List — Frontend:**
- [ ] `types/invoice.ts`
- [ ] `services/invoiceService.ts`
- [ ] `pages/payments/InvoiceListPage.vue` — filter status & bulan
- [ ] `pages/payments/InvoiceDetailPage.vue`
- [ ] `components/payment/InvoiceCard.vue`
- [ ] `components/payment/PaymentStatus.vue` — badge status reusable

**Kriteria Selesai:**
- [ ] Artisan command berhasil generate invoice untuk semua kontrak aktif
- [ ] Invoice number ter-generate otomatis dengan format `INV-YYYY-NNNN`
- [ ] Vue: list invoice bisa difilter berdasarkan status, properti, dan bulan
- [ ] Vue: badge status invoice tampil dengan warna semantik yang tepat
- [ ] PDF invoice bisa di-download

---

### 3.2 Integrasi Payment Gateway

**API Contract:**

```
POST   /api/v1/payments/create-transaction   ← buat transaksi, return token
POST   /api/v1/payments/webhook/midtrans     ← endpoint webhook (public, no auth)
POST   /api/v1/payments/webhook/xendit
GET    /api/v1/payments/{invoiceId}/status
```

**POST /api/v1/payments/create-transaction — Request:**
```json
{
  "invoice_id": "uuid",
  "gateway": "midtrans"
}
```

**POST /api/v1/payments/create-transaction — Response `200`:**
```json
{
  "data": {
    "transaction_token": "abc123...",
    "redirect_url": "https://app.midtrans.com/...",
    "invoice_id": "uuid"
  },
  "message": "Transaction created"
}
```

**Task List — Backend:**
- [ ] `PaymentController`, `PaymentService`
- [ ] Integrasi Midtrans SDK (atau manual HTTP call)
- [ ] `HandleMidtransWebhookJob` — queued, update status invoice ke `paid`
- [ ] Verifikasi signature webhook dari Midtrans
- [ ] Migration: tambah kolom `payment_token`, `paid_at`, `payment_gateway` di `invoices`

**Task List — Frontend:**
- [ ] `services/paymentService.ts` — fungsi `createTransaction()`
- [ ] `pages/payments/PaymentPage.vue` — tombol bayar, embed Midtrans Snap widget
- [ ] Setelah pembayaran → polling `GET /api/v1/payments/{invoiceId}/status` setiap 3 detik
- [ ] Tampilkan halaman sukses atau gagal berdasarkan status

**Kriteria Selesai:**
- [ ] `POST /api/v1/payments/create-transaction` → token Midtrans dikembalikan
- [ ] Midtrans Snap widget muncul di frontend menggunakan token
- [ ] Webhook Midtrans diterima → status invoice berubah ke `paid`
- [ ] Secret key Midtrans tidak pernah muncul di kode frontend
- [ ] Vue: halaman payment menampilkan detail invoice sebelum pembayaran
- [ ] Vue: setelah bayar sukses → halaman menampilkan konfirmasi

---

### 3.3 Laporan Keuangan

**API Contract:**

```
GET    /api/v1/reports/financial              ← summary keseluruhan
GET    /api/v1/reports/financial/{propertyId} ← per properti
GET    /api/v1/reports/financial/export       ← download PDF laporan
```

**GET /api/v1/reports/financial — Query Params:**
```
?year=2025&month=02
```

**GET /api/v1/reports/financial — Response `200`:**
```json
{
  "data": {
    "period": "2025-02",
    "total_invoiced": 45000000,
    "total_collected": 37500000,
    "total_outstanding": 7500000,
    "collection_rate": 83.3,
    "by_property": [
      {
        "property_id": "uuid",
        "property_name": "Kos Harmoni",
        "invoiced": 15000000,
        "collected": 15000000,
        "outstanding": 0
      }
    ]
  },
  "message": "Success"
}
```

**Task List — Backend:**
- [ ] `ReportController`, `ReportService`
- [ ] Query agregasi dari tabel `invoices` dengan filter periode
- [ ] `GenerateFinancialReportJob` — PDF dengan Spatie PDF (queued)

**Task List — Frontend:**
- [ ] `pages/reports/FinancialReportPage.vue`
- [ ] Chart pendapatan bulanan menggunakan `vue-chartjs`
- [ ] Filter tahun dan bulan
- [ ] Tombol export → download PDF

**Kriteria Selesai:**
- [ ] Data laporan akurat sesuai data invoice yang ada
- [ ] Chart menampilkan tren pendapatan 12 bulan terakhir
- [ ] PDF laporan bisa di-generate dan di-download
- [ ] Vue: filter tahun/bulan mengubah data chart secara reaktif

---

## FASE 4 — Komunikasi & Pengalaman Pengguna

> Tujuan: Sistem notifikasi berjalan, tenant bisa mengajukan keluhan, dan data tampil secara real-time.

---

### 4.1 Sistem Tiket Keluhan

**API Contract:**

```
GET    /api/v1/tickets
POST   /api/v1/tickets
GET    /api/v1/tickets/{id}
PUT    /api/v1/tickets/{id}/status    ← update status oleh agent/admin
POST   /api/v1/tickets/{id}/comments  ← tambah komentar
```

**POST /api/v1/tickets — Request:**
```json
{
  "property_id": "uuid",
  "category": "maintenance",
  "title": "AC rusak di kamar 3",
  "description": "AC tidak dingin sejak 2 hari lalu",
  "priority": "medium"
}
```

**GET /api/v1/tickets/{id} — Response `200`:**
```json
{
  "data": {
    "id": "uuid",
    "ticket_number": "TKT-2025-0042",
    "status": "open",
    "priority": "medium",
    "category": "maintenance",
    "title": "AC rusak di kamar 3",
    "description": "...",
    "submitted_by": { "id": "uuid", "name": "Ani Wijaya" },
    "assigned_to": null,
    "comments": [],
    "created_at": "2025-01-01T00:00:00Z"
  },
  "message": "Success"
}
```

**Task List — Backend:**
- [x] Migration: `tickets`, `ticket_comments` table
- [x] `Ticket` model dengan status: `open`, `in_progress`, `resolved`, `closed`
- [x] `TicketController`, `TicketService`
- [x] `TicketPolicy` — tenant hanya bisa lihat tiket miliknya

**Task List — Frontend:**
- [x] `pages/tickets/TicketListPage.vue`
- [x] `pages/tickets/TicketDetailPage.vue` — dengan thread komentar
- [x] `pages/tickets/TicketFormPage.vue`

**Kriteria Selesai:**
- [x] Tenant bisa membuat tiket baru
- [x] Agent/admin bisa mengubah status tiket
- [x] Tenant hanya bisa melihat tiket miliknya → `403` jika coba akses tiket orang lain
- [x] Vue: thread komentar tampil secara kronologis

---

### 4.2 Notifikasi

**API Contract:**

```
GET    /api/v1/notifications           ← list notifikasi user yang login
PUT    /api/v1/notifications/read-all  ← tandai semua sudah dibaca
PUT    /api/v1/notifications/{id}/read
```

**GET /api/v1/notifications — Response `200`:**
```json
{
  "data": [
    {
      "id": "uuid",
      "type": "invoice_due",
      "title": "Invoice Jatuh Tempo",
      "message": "Invoice INV-2025-0012 jatuh tempo 3 hari lagi",
      "data": { "invoice_id": "uuid" },
      "read_at": null,
      "created_at": "2025-01-01T00:00:00Z"
    }
  ],
  "meta": { "unread_count": 3 },
  "message": "Success"
}
```

**Notifikasi yang dikirim (via Laravel Queue):**

| Event | Kanal | Penerima |
|---|---|---|
| Invoice dibuat | Email + WhatsApp | Tenant |
| Invoice jatuh tempo 3 hari lagi | Email + WhatsApp | Tenant |
| Pembayaran diterima | Email | Tenant |
| Kontrak berakhir 30 hari lagi | Email | Owner + Tenant |
| Tiket baru masuk | In-app | Agent/Admin |
| Status tiket berubah | In-app + Email | Tenant |

**Task List — Backend:**
- [ ] `NotificationController`
- [ ] Notification classes: `InvoiceDueNotification`, `PaymentConfirmedNotification`, `ContractExpiringNotification`, `TicketStatusChangedNotification`
- [ ] Channel Fonnte WhatsApp di `app/Notifications/Channels/FonnteChannel.php`
- [ ] Semua notifikasi via queue — driver `redis`

**Task List — Frontend:**
- [ ] `stores/notification.ts` — state: `notifications`, `unreadCount`
- [ ] `composables/useNotification.ts`
- [ ] Notification bell di layout header — menampilkan `unreadCount`
- [ ] Dropdown notifikasi — klik item → mark as read → navigate ke halaman terkait

**Kriteria Selesai:**
- [ ] Email notifikasi terkirim saat invoice dibuat (test via Mailtrap)
- [ ] WhatsApp notifikasi terkirim via Fonnte API
- [ ] Semua notifikasi diproses via queue, tidak synchronous
- [ ] Vue: badge unread count di navbar update otomatis
- [ ] Vue: klik notifikasi → navigasi ke halaman yang relevan

---

### 4.3 Real-time dengan Laravel Reverb

**API Contract (WebSocket Events):**

```
channel: private-user.{userId}
  event: NotificationSent       ← notifikasi baru masuk

channel: private-ticket.{ticketId}
  event: TicketStatusUpdated    ← status tiket berubah

channel: private-invoice.{invoiceId}
  event: PaymentConfirmed       ← pembayaran dikonfirmasi
```

**Task List — Backend:**
- [ ] Install dan konfigurasi Laravel Reverb
- [ ] Event classes: `NotificationSent`, `TicketStatusUpdated`, `PaymentConfirmed`
- [ ] Broadcast setiap event ke channel yang tepat
- [ ] Konfigurasi `broadcasting.php` untuk Reverb

**Task List — Frontend:**
- [ ] Install `laravel-echo` dan `pusher-js`
- [ ] `composables/useEcho.ts` — setup koneksi Reverb dari `VITE_REVERB_*` env
- [ ] Subscribe ke `private-user.{userId}` saat login → update `notificationStore` jika ada event baru
- [ ] Subscribe ke `private-invoice.{invoiceId}` di halaman payment → update status otomatis tanpa polling

**Kriteria Selesai:**
- [ ] Koneksi WebSocket ke Reverb berhasil
- [ ] Notifikasi baru muncul di navbar secara real-time tanpa refresh halaman
- [ ] Status invoice di halaman payment update secara real-time setelah pembayaran
- [ ] Kredensial Reverb hanya dibaca dari `VITE_REVERB_*` env variables

---

## Ringkasan Urutan Fase

```
FASE 1 (Fondasi)
  1.1 Setup Project & Infrastructure
  1.2 Authentication
       ↓
FASE 2 (Core Domain)
  2.1 Manajemen Properti
  2.2 Manajemen Tenant
  2.3 Kontrak Sewa
       ↓
FASE 3 (Transaksi Keuangan)
  3.1 Invoice & Billing
  3.2 Payment Gateway
  3.3 Laporan Keuangan
       ↓
FASE 4 (Komunikasi)
  4.1 Tiket Keluhan
  4.2 Notifikasi
  4.3 Real-time (Reverb)
```

---

## Aturan Global yang Berlaku di Setiap Fase

Berikut adalah aturan yang tidak boleh dilanggar pada fitur apapun:

**API Contract dulu, baru kode.** Setiap fitur dimulai dengan mendefinisikan endpoint, request, dan response shape sebelum menulis satu baris kode backend maupun frontend.

**Test API di Postman sebelum menyambungkan ke Vue.** Jangan pernah mulai menulis kode frontend sebelum bisa membuktikan bahwa API bekerja dengan benar.

**Controller tipis, logika di Service.** Tidak ada database query di controller. Tidak ada business logic di Pinia store.

**Semua notifikasi dan PDF via Queue.** Tidak ada operasi berat yang dijalankan synchronous dalam request lifecycle.

**Secret key tidak pernah ada di frontend.** Semua operasi sensitif (payment, notifikasi) hanya dari backend.

---

*Dokumen ini harus diperbarui jika ada perubahan arsitektur atau keputusan teknis baru selama pengembangan.*
