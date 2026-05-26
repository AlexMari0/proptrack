# 🏢 Dokumentasi Alur Aplikasi PropTrack

Dokumen ini menjelaskan arsitektur sistem, siklus hidup data (*data lifecycle*), dan alur kerja (*workflow*) end-to-end secara komprehensif pada platform PropTrack.

---

## 🏗️ 1. Arsitektur Komunikasi Terpisah (Decoupled Architecture)

PropTrack menggunakan arsitektur client-server terpisah secara penuh (*fully decoupled*). Interaksi antar komponen didesain sebagai berikut:

```mermaid
graph TD
    subgraph Frontend [PropTrack Web — Vue 3 SPA]
        V[Vue Components / Pages] <--> C[Composables]
        C <--> S[Pinia Stores]
        C <--> A[Axios HTTP Client]
        E[Laravel Echo] <--> ReverbListener[Reverb WS Client]
    end

    subgraph Backend [PropTrack API — Laravel 12]
        Routes[API Routes] --> Ctrl[Controllers]
        Ctrl --> Request[Form Requests]
        Ctrl --> Serv[Services]
        Serv --> DB[(Database SQLite)]
        Serv --> Queue[Redis Queue / Horizon]
        Queue --> Jobs[Background Jobs]
        Serv --> Broadcast[Broadcasting Events]
    end

    subgraph External [Layanan Eksternal]
        Midtrans[Midtrans Payment Gateway]
        Fonnte[Fonnte WhatsApp API]
    end

    A <-->|HTTP/JSON + Bearer Token| Routes
    ReverbListener <-->|WebSockets| Broadcast
    Serv <-->|HTTP API| Midtrans
    Jobs -->|SMS/WA Gateway| Fonnte
```

### Aturan Komunikasi Utama:
1. **Autentikasi Stateless**: Tidak ada session berbasis cookie di sisi server. Otentikasi menggunakan **Laravel Sanctum Bearer Token** yang dikirimkan secara otomatis di setiap *request* melalui Axios Interceptor.
2. **Pola Delegasi Bisnis**:
   $$\text{Controller} \longrightarrow \text{FormRequest (Validasi)} \longrightarrow \text{Service (Logika Bisnis)} \longrightarrow \text{Resource (JSON Wrapper)}$$

---

## 🔑 2. Alur Autentikasi & Otorisasi (RBAC)

PropTrack mendukung kontrol akses berbasis peran (*Role-Based Access Control* - RBAC) menggunakan paket **Spatie Permission**. Peran pengguna terdiri dari: `admin`, `owner` (pemilik), `agent` (agen dukungan), dan `tenant` (penyewa).

```mermaid
sequenceDiagram
    autonumber
    actor User as Pengguna (Vue SPA)
    participant API as PropTrack API
    participant Store as Pinia (auth.ts)
    participant LS as LocalStorage

    User->>API: 1. Kirim Kredensial (POST /auth/login)
    Note over API: Validasi Kredensial & Buat Sanctum Token
    API-->>User: 2. Kembalikan Token & Profil Pengguna (dengan Peran/Roles)
    User->>Store: 3. Set Profil & Token ke State
    User->>LS: 4. Simpan Token ("proptrack_token")
    Note over User: Axios Interceptor menyisipkan token secara otomatis ke semua request selanjutnya
```

*   **Pencegahan Rute Frontend**: Rute Vue Router dilindungi oleh *navigation guards* di `src/router/index.ts` untuk mengarahkan pengguna non-autentikasi ke halaman `/login`.
*   **Sumber Kebenaran Otorisasi**: Validasi otorisasi selalu diverifikasi di sisi backend menggunakan **Laravel Policies** sebelum melakukan aksi database apa pun.

---

## 📋 3. Alur Manajemen Properti, Penyewa, & Kontrak

Aturan bisnis krusial: **Satu properti hanya boleh memiliki maksimal satu kontrak sewa yang aktif secara bersamaan.**

```mermaid
sequenceDiagram
    autonumber
    actor Owner as Pemilik (Vue SPA)
    participant API as PropTrack API
    participant DB as Database SQLite
    participant Queue as Redis Queue (Jobs)

    Owner->>API: 1. Buat Properti & Unggah Foto (POST /properties)
    API->>DB: Simpan data koordinat peta & asosiasi foto via Spatie Media Library
    Owner->>API: 2. Daftarkan Profil Penyewa dengan 16-Digit KTP (POST /tenants)
    Owner->>API: 3. Tautkan Kontrak Sewa (POST /contracts)
    Note over API: Validasi ContractService::assertNoActiveContractForProperty()
    API->>DB: Simpan Kontrak (status = active)
    API->>Queue: 4. Dispatch GenerateContractPdfJob (Asinkron)
    API-->>Owner: 5. Kembalikan Respon Sukses (ContractResource)
    Note over Queue: Rendisi PDF bilingual menggunakan Spatie Laravel PDF secara asinkron
```

> [!IMPORTANT]
> Kontrak sewa harus bilingual (Bahasa Indonesia & Inggris), berisi rincian harga sewa, jumlah deposit, dan tanggal penagihan bulanan yang dibatasi dari tanggal 1 hingga 28 demi konsistensi perhitungan kalender.

---

## 💳 4. Alur Penagihan Bulanan & Gerbang Pembayaran (Midtrans)

Setiap bulan, sistem membuat tagihan sewa baru untuk setiap penyewa aktif. Pembayaran diintegrasikan secara aman menggunakan **Midtrans Snap API**.

```mermaid
sequenceDiagram
    autonumber
    actor Tenant as Penyewa (Vue SPA)
    participant API as PropTrack API
    participant DB as Database SQLite
    participant Midtrans as Midtrans Snap API
    participant Reverb as Laravel Reverb (WebSocket)

    Note over API: Scheduler (Cron) Memulai Siklus Bulanan
    API->>DB: Buat Invoice Baru (status = unpaid)
    API->>Tenant: Kirim Notifikasi In-App, Email, & WhatsApp (Fonnte)
    
    Tenant->>API: 1. Klik Pembayaran (POST /payments/create-transaction)
    API->>Midtrans: 2. Request Token Transaksi Baru via Snap API
    Midtrans-->>API: 3. Kembalikan Snap Token
    API-->>Tenant: 4. Kirim Snap Token ke Klien
    Tenant->>Midtrans: 5. Render Modal Snap & Selesaikan Pembayaran Online
    Midtrans-->>Tenant: 6. Status Berhasil di Sisi Klien
    
    Note over Midtrans: Proses Verifikasi Pembayaran Asinkron
    Midtrans->>API: 7. Kirim Webhook (POST /payments/webhook/midtrans)
    Note over API: Verifikasi Signature Key & Update Status Invoice ke "paid"
    API->>Reverb: 8. Broadcast Event PaymentConfirmed
    Reverb-->>Tenant: 9. Transisi UI Seketika ke Layar Sukses Pembayaran
```

*   **Pencegahan Inkonsistensi Pembayaran**: Penggunaan Webhook wajib diverifikasi keasliannya menggunakan *Signature Key* dari Midtrans sebelum database diperbarui untuk mencegah manipulasi status.
*   **Asinkronitas Laporan**: Setelah invoice dibayar, sistem memicu pembaharuan data analisis keuangan secara otomatis.

---

## 🛠️ 5. Alur Tiket Keluhan & Thread Komentar

Penyewa yang mengalami kendala teknis (seperti AC rusak, kebocoran, dll.) dapat mengajukan tiket bantuan yang akan diproses secara real-time oleh agen dukungan.

```mermaid
graph TD
    T[Penyewa mengajukan tiket keluhan] -->|Validasi Kontrak Aktif| TS[TicketService memvalidasi hak sewa]
    TS -->|DB lockForUpdate| GenNum[Buat Nomor Tiket Unik TKT-YYYY-XXXX]
    GenNum --> SaveDB[Simpan Tiket ke DB & status = open]
    SaveDB --> Notify[Kirim Notifikasi NewTicket ke Agen & Admin]
    
    Agent[Agen Dukungan masuk dasbor] --> Claim[Klaim tiket & ubah status ke In Progress]
    Claim --> BroadUpdate[Broadcast event TicketStatusUpdated via Reverb]
    BroadUpdate -->|Live UI| RefreshT[UI Penyewa terupdate otomatis secara real-time]
    
    Agent & Tenant <-->|Thread Dua Kolom| Comment[Tambahkan komentar baru & Balas Utas]
```

### Fitur Pengaman Integritas:
*   **Thread-Safety**: Penomoran tiket (`TKT-YYYY-XXXX`) dihitung di dalam transaksi database yang dikunci menggunakan kueri `lockForUpdate()` untuk mencegah kondisi balapan (*race condition*) ketika beberapa penyewa mengirim tiket bersamaan.
*   **Keamanan Saluran Chat**: Komunikasi utas keluhan disiarkan via *Private Channel* Websocket Laravel Reverb yang membutuhkan otorisasi Sanctum aktif.

---

## 📈 6. Alur Pelaporan Keuangan Pemilik (Financial Report)

Pemilik dapat memantau produktivitas investasi properti mereka melalui visualisasi dasbor interaktif.

```
+-------------------------------------------------------------------------+
|                              REPORT SERVICE                             |
|                                                                         |
|  1. Ambil semua invoice aktif (Kecuali status = cancelled)              |
|  2. Terapkan filter berdasarkan Peran (Owner hanya melihat properti     |
|     miliknya, Admin melihat keseluruhan)                                |
|  3. Terapkan filter periode tanggal tahun/bulan                        |
|  4. Kalkulasi metrik agregat secara efisien (Helper: calculateMetrics)   |
|  5. Kelompokkan berdasarkan properti untuk data sebaran                 |
|  6. Format data terstruktur dikembalikan melalui ReportResource         |
+-------------------------------------------------------------------------+
                                   |
         +-------------------------+-------------------------+
         |                                                   |
         v                                                   v
[Grafik 12 Bulan (Vite/ChartJS)]                   [Ekspor PDF / CSV]
Visualisasi rasio koleksi, total             Rendisi asinkron template Blade
tagihan, & total uang terkumpul              menjadi dokumen PDF resmi.
```

---

## 🏁 Ringkasan Siklus Hidup Alur Utama (End-to-End)

```
[Pemilik Properti]                     [Penyewa (Tenant)]                 [Agen / Admin]
       │                                       │                                │
       ├─► Buat Properti & Galeri Foto         │                                │
       ├─► Daftarkan Penyewa (KTP)             │                                │
       ├─► Tanda Tangan Kontrak Sewa ──────────┼───► Terima Email Kontrak (PDF) │
       │                                       │                                │
       ├─► (Siklus Bulanan / Cron)             │                                │
       │   Buat Tagihan Sewa Bulanan ──────────┼───► Terima Notifikasi In-app   │
       │                                       │     & WhatsApp Link Tagihan    │
       │                                       │                                │
       │                                       ├─► Bayar Tagihan (Midtrans Snap)│
       │                                       │   Konfirmasi Pembayaran        │
       │   Terima Laporan Analisis             │   Diterima secara Live         │
       │   Keuangan Update (Grafik/PDF) ◄──────┤                                │
       │                                       │                                │
       │                                       ├─► Kirim Tiket AC Rusak ───────►├─► Klaim Tiket
       │                                       │                                ├─► Set "In Progress"
       │                                       │   Terima Live Status Update ◄──┤
       │                                       │                                │
       │                                       ├─► Diskusi Utas Chat ◄─────────►├─► Balas Utas Chat
       │                                       │                                ├─► Selesaikan Tiket
       │                                       │   Terima Update Selesai ◄──────┤   (Ubah status -> Resolved)
```
