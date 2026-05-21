<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; }
  .page { padding: 48px; max-width: 794px; margin: 0 auto; }

  /* Header */
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
  .brand { font-size: 22px; font-weight: 700; color: #6366f1; letter-spacing: -0.5px; }
  .brand-sub { font-size: 10px; color: #6b7280; margin-top: 2px; }
  .invoice-meta { text-align: right; }
  .invoice-number { font-size: 18px; font-weight: 700; color: #1a1a2e; }
  .invoice-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.1em; color: #6b7280; margin-bottom: 4px; }

  /* Status badge */
  .status-badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 6px; }
  .status-unpaid  { background: #fef3c7; color: #d97706; }
  .status-paid    { background: #d1fae5; color: #059669; }
  .status-overdue { background: #fee2e2; color: #dc2626; }
  .status-cancelled { background: #f3f4f6; color: #6b7280; }

  /* Divider */
  .divider { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }

  /* Parties section */
  .parties { display: flex; gap: 48px; margin-bottom: 32px; }
  .party { flex: 1; }
  .party-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #6b7280; margin-bottom: 6px; }
  .party-name { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-bottom: 2px; }
  .party-detail { font-size: 11px; color: #6b7280; line-height: 1.5; }

  /* Invoice details table */
  .details-table { width: 100%; border-collapse: collapse; margin-bottom: 32px; }
  .details-table th { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; padding: 10px 12px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; text-align: left; }
  .details-table td { padding: 12px; border-bottom: 1px solid #f3f4f6; font-size: 11px; color: #374151; }
  .details-table tr:last-child td { border-bottom: none; }

  /* Amount box */
  .amount-box { background: #f0f0ff; border: 1px solid #c7d2fe; border-radius: 12px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
  .amount-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #6366f1; }
  .amount-value { font-size: 24px; font-weight: 700; color: #4f46e5; }

  /* Payment info */
  .payment-info { background: #f9fafb; border-radius: 10px; padding: 16px; margin-bottom: 32px; font-size: 11px; color: #6b7280; line-height: 1.7; }
  .payment-info strong { color: #374151; }

  /* Paid stamp */
  .paid-stamp { text-align: center; margin-bottom: 24px; }
  .paid-stamp-inner { display: inline-block; border: 3px solid #059669; border-radius: 8px; padding: 10px 28px; color: #059669; font-size: 20px; font-weight: 700; letter-spacing: 0.15em; transform: rotate(-4deg); opacity: 0.9; }

  /* Footer */
  .footer { text-align: center; font-size: 9px; color: #9ca3af; margin-top: 40px; padding-top: 16px; border-top: 1px solid #f3f4f6; line-height: 1.6; }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    <div>
      <div class="brand">PropTrack</div>
      <div class="brand-sub">Property Management Platform</div>
    </div>
    <div class="invoice-meta">
      <div class="invoice-label">Nomor Invoice</div>
      <div class="invoice-number">{{ $invoice->invoice_number }}</div>
      <span class="status-badge status-{{ $invoice->status }}">
        @switch($invoice->status)
          @case('unpaid')     Belum Dibayar  @break
          @case('paid')       Lunas          @break
          @case('overdue')    Jatuh Tempo    @break
          @case('cancelled')  Dibatalkan     @break
        @endswitch
      </span>
    </div>
  </div>

  <hr class="divider" />

  <!-- Parties -->
  <div class="parties">
    <div class="party">
      <div class="party-label">Tagihan Kepada</div>
      <div class="party-name">{{ $invoice->tenant->name }}</div>
      <div class="party-detail">
        {{ $invoice->tenant->email }}<br />
        {{ $invoice->tenant->phone }}
      </div>
    </div>
    <div class="party">
      <div class="party-label">Properti</div>
      <div class="party-name">{{ $invoice->property->name }}</div>
      <div class="party-detail">{{ $invoice->property->address ?? '-' }}</div>
    </div>
    <div class="party">
      <div class="party-label">Tanggal Invoice</div>
      <div class="party-name">{{ $invoice->created_at->translatedFormat('d F Y') }}</div>
      <div class="party-detail" style="margin-top:8px;">
        <strong>Jatuh Tempo:</strong><br />
        {{ $invoice->due_date->translatedFormat('d F Y') }}
      </div>
    </div>
  </div>

  <!-- Invoice details -->
  <table class="details-table">
    <thead>
      <tr>
        <th>Keterangan</th>
        <th>Periode</th>
        <th style="text-align:right;">Jumlah</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Sewa Bulanan — {{ $invoice->property->name }}</td>
        <td>
          @php
            $month = \Carbon\Carbon::createFromFormat('Y-m', $invoice->billing_month);
          @endphp
          {{ $month->translatedFormat('F Y') }}
        </td>
        <td style="text-align:right;">
          Rp {{ number_format($invoice->amount, 0, ',', '.') }}
        </td>
      </tr>
    </tbody>
  </table>

  <!-- Amount box -->
  <div class="amount-box">
    <div class="amount-label">Total Tagihan</div>
    <div class="amount-value">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</div>
  </div>

  <!-- Paid stamp (only if paid) -->
  @if($invoice->isPaid())
  <div class="paid-stamp">
    <div class="paid-stamp-inner">✓ LUNAS</div>
    @if($invoice->paid_at)
    <p style="margin-top:8px; font-size:10px; color:#6b7280;">
      Dibayar pada {{ $invoice->paid_at->translatedFormat('d F Y, H:i') }} WIB
      @if($invoice->payment_gateway) via {{ strtoupper($invoice->payment_gateway) }} @endif
    </p>
    @endif
  </div>
  @else
  <!-- Payment info (only if unpaid/overdue) -->
  <div class="payment-info">
    <strong>Informasi Pembayaran</strong><br />
    Silakan lakukan pembayaran sebelum tanggal jatuh tempo.
    Hubungi pemilik properti jika memerlukan informasi rekening pembayaran.
  </div>
  @endif

  <!-- Footer -->
  <div class="footer">
    Invoice ini diterbitkan secara otomatis oleh sistem PropTrack.<br />
    Untuk pertanyaan, hubungi pengelola properti Anda.<br />
    <strong>{{ $invoice->invoice_number }}</strong> · Diterbitkan {{ $invoice->created_at->translatedFormat('d F Y') }}
  </div>

</div>
</body>
</html>
