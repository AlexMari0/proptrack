<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kontrak Sewa — {{ $contract->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a2e;
            background: #fff;
            padding: 48px 56px;
            line-height: 1.6;
        }

        /* Header */
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #6366f1;
        }

        .header__brand { font-size: 22pt; font-weight: 800; color: #6366f1; letter-spacing: -0.5px; }
        .header__subtitle { font-size: 9pt; color: #64748b; margin-top: 4px; }

        .header__meta { text-align: right; font-size: 9pt; color: #64748b; }
        .header__meta strong { display: block; font-size: 11pt; color: #1a1a2e; font-weight: 700; }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 28px;
        }
        .status-active      { background: #dcfce7; color: #16a34a; }
        .status-expired     { background: #f1f5f9; color: #64748b; }
        .status-terminated  { background: #fee2e2; color: #dc2626; }

        /* Section */
        .section { margin-bottom: 28px; }
        .section__title {
            font-size: 8pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6366f1;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Grid */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 32px; }

        .field { margin-bottom: 12px; }
        .field__label { font-size: 8pt; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .field__value { font-size: 11pt; color: #1a1a2e; font-weight: 500; margin-top: 2px; }
        .field__value--mono { font-family: 'Courier New', monospace; font-size: 10pt; }
        .field__value--price { font-size: 13pt; font-weight: 800; color: #6366f1; }

        /* Divider */
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }

        /* Terms */
        .terms { font-size: 9pt; color: #64748b; line-height: 1.7; }
        .terms ol { padding-left: 18px; }
        .terms li { margin-bottom: 6px; }

        /* Signature block */
        .signature-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-top: 48px;
        }

        .signature__party { font-size: 9pt; color: #64748b; }
        .signature__party strong { display: block; font-size: 10pt; color: #1a1a2e; margin-bottom: 4px; }
        .signature__line {
            height: 60px;
            border-bottom: 2px solid #1a1a2e;
            margin: 16px 0 8px;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 8pt;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div>
            <div class="header__brand">PropTrack</div>
            <div class="header__subtitle">Sistem Manajemen Properti</div>
        </div>
        <div class="header__meta">
            <strong>KONTRAK SEWA</strong>
            No: {{ strtoupper(substr($contract->id, 0, 8)) }}<br>
            Tanggal: {{ $contract->created_at->translatedFormat('d F Y') }}
        </div>
    </div>

    <!-- Status -->
    @php
        $statusClass = match($contract->status) {
            'active'     => 'status-active',
            'expired'    => 'status-expired',
            'terminated' => 'status-terminated',
            default      => 'status-expired',
        };
        $statusLabel = match($contract->status) {
            'active'     => 'Aktif',
            'expired'    => 'Berakhir',
            'terminated' => 'Dihentikan',
            default      => $contract->status,
        };
    @endphp

    <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>

    <!-- Parties -->
    <div class="section">
        <div class="section__title">Para Pihak</div>
        <div class="grid-2">
            <div>
                <div class="field">
                    <div class="field__label">Nama Penyewa</div>
                    <div class="field__value">{{ $contract->tenant->name }}</div>
                </div>
                <div class="field">
                    <div class="field__label">No. KTP</div>
                    <div class="field__value field__value--mono">{{ $contract->tenant->id_card_number }}</div>
                </div>
                <div class="field">
                    <div class="field__label">Email</div>
                    <div class="field__value">{{ $contract->tenant->email }}</div>
                </div>
                <div class="field">
                    <div class="field__label">Telepon</div>
                    <div class="field__value">{{ $contract->tenant->phone }}</div>
                </div>
            </div>
            <div>
                <div class="field">
                    <div class="field__label">Nama Properti</div>
                    <div class="field__value">{{ $contract->property->name }}</div>
                </div>
                <div class="field">
                    <div class="field__label">Alamat</div>
                    <div class="field__value">{{ $contract->property->address }}</div>
                </div>
                <div class="field">
                    <div class="field__label">Jenis Properti</div>
                    <div class="field__value">{{ ucfirst($contract->property->type) }}</div>
                </div>
            </div>
        </div>
    </div>

    <hr class="divider" />

    <!-- Financial Terms -->
    <div class="section">
        <div class="section__title">Ketentuan Keuangan</div>
        <div class="grid-2">
            <div>
                <div class="field">
                    <div class="field__label">Harga Sewa per Bulan</div>
                    <div class="field__value field__value--price">
                        {{ number_format($contract->monthly_rent, 0, ',', '.') }} IDR
                    </div>
                </div>
                <div class="field">
                    <div class="field__label">Deposit</div>
                    <div class="field__value">{{ number_format($contract->deposit_amount, 0, ',', '.') }} IDR</div>
                </div>
                <div class="field">
                    <div class="field__label">Tanggal Tagihan</div>
                    <div class="field__value">Setiap tanggal {{ $contract->billing_date }}</div>
                </div>
            </div>
            <div>
                <div class="field">
                    <div class="field__label">Tanggal Mulai</div>
                    <div class="field__value">{{ $contract->start_date->translatedFormat('d F Y') }}</div>
                </div>
                <div class="field">
                    <div class="field__label">Tanggal Berakhir</div>
                    <div class="field__value">{{ $contract->end_date->translatedFormat('d F Y') }}</div>
                </div>
                <div class="field">
                    <div class="field__label">Durasi</div>
                    <div class="field__value">
                        {{ $contract->start_date->diffInMonths($contract->end_date) }} bulan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr class="divider" />

    <!-- Terms -->
    <div class="section">
        <div class="section__title">Syarat dan Ketentuan</div>
        <div class="terms">
            <ol>
                <li>Penyewa wajib membayar uang sewa selambat-lambatnya pada tanggal {{ $contract->billing_date }} setiap bulannya.</li>
                <li>Deposit sebesar {{ number_format($contract->deposit_amount, 0, ',', '.') }} IDR akan dikembalikan setelah masa sewa berakhir dan kondisi properti diperiksa.</li>
                <li>Penyewa dilarang melakukan perubahan struktural pada properti tanpa persetujuan tertulis dari pemilik.</li>
                <li>Pemberitahuan pengakhiran kontrak harus dilakukan minimal 30 hari sebelum tanggal berakhir.</li>
                <li>Pelanggaran terhadap ketentuan ini dapat mengakibatkan pemutusan kontrak secara sepihak.</li>
            </ol>
        </div>
    </div>

    <!-- Signatures -->
    <div class="signature-block">
        <div class="signature__party">
            <strong>Pemilik Properti</strong>
            PropTrack Management
            <div class="signature__line"></div>
            Nama &amp; Tanda Tangan
        </div>
        <div class="signature__party">
            <strong>Penyewa</strong>
            {{ $contract->tenant->name }}
            <div class="signature__line"></div>
            Nama &amp; Tanda Tangan
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <span>Dokumen ini dibuat secara otomatis oleh sistem PropTrack.</span>
        <span>ID Kontrak: {{ $contract->id }}</span>
    </div>

</body>
</html>
