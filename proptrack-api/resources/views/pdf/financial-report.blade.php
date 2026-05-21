<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Laporan Keuangan / Financial Report - {{ $data['period'] }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a2e; background: #fff; }
  .page { padding: 40px; max-width: 794px; margin: 0 auto; }

  /* Header */
  .header { margin-bottom: 30px; }
  .brand { font-size: 24px; font-weight: 700; color: #4f46e5; letter-spacing: -0.5px; }
  .brand-sub { font-size: 10px; color: #6b7280; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.1em; }
  
  .report-title-container { margin-top: 15px; }
  .report-title { font-size: 18px; font-weight: 700; color: #1a1a2e; }
  .report-subtitle { font-size: 12px; color: #6b7280; margin-top: 2px; }

  /* Divider */
  .divider { border: none; border-top: 2px solid #e5e7eb; margin: 20px 0; }

  /* Summary Cards */
  .summary-container { margin-bottom: 30px; width: 100%; display: table; }
  .summary-card { display: table-cell; width: 25%; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; text-align: center; }
  .summary-card:not(:last-child) { border-right: none; }
  .summary-label { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; margin-bottom: 6px; }
  .summary-value { font-size: 14px; font-weight: 700; color: #1a1a2e; }
  .summary-card.highlight { background: #f0f0ff; border-color: #c7d2fe; }
  .summary-card.highlight .summary-label { color: #6366f1; }
  .summary-card.highlight .summary-value { color: #4f46e5; font-size: 16px; }

  /* Details table */
  .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #374151; margin-bottom: 12px; }
  .details-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
  .details-table th { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; padding: 10px 12px; background: #f9fafb; border-bottom: 2px solid #e5e7eb; text-align: left; }
  .details-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; font-size: 10px; color: #374151; }
  .details-table tr:last-child td { border-bottom: 2px solid #d1d5db; }
  
  /* Footer */
  .footer { text-align: center; font-size: 8px; color: #9ca3af; margin-top: 50px; padding-top: 15px; border-top: 1px solid #f3f4f6; line-height: 1.6; }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    <div style="float: left;">
      <div class="brand">PropTrack</div>
      <div class="brand-sub">Property Management Platform</div>
    </div>
    <div style="float: right; text-align: right;">
      <div class="report-title">LAPORAN KEUANGAN</div>
      <div class="report-subtitle">FINANCIAL REPORT</div>
    </div>
    <div style="clear: both;"></div>
  </div>

  <hr class="divider" />

  <div class="report-title-container">
    <div style="font-size: 13px; font-weight: bold; color: #1f2937;">
      Periode Laporan / Report Period: <span style="color: #4f46e5;">{{ $data['period'] }}</span>
    </div>
    <div style="font-size: 10px; color: #6b7280; margin-top: 4px;">
      Dicetak pada / Printed at: {{ now()->translatedFormat('d F Y, H:i') }} WIB
    </div>
  </div>

  <div style="margin-top: 25px;"></div>

  <!-- Summary Cards -->
  <div class="summary-container">
    <div class="summary-card">
      <div class="summary-label">Total Tagihan<br><span style="text-transform:none; font-weight:normal; font-size:7px; color:#9ca3af;">Total Invoiced</span></div>
      <div class="summary-value">Rp {{ number_format($data['total_invoiced'], 0, ',', '.') }}</div>
    </div>
    <div class="summary-card">
      <div class="summary-label">Total Diterima<br><span style="text-transform:none; font-weight:normal; font-size:7px; color:#9ca3af;">Total Collected</span></div>
      <div class="summary-value" style="color: #059669;">Rp {{ number_format($data['total_collected'], 0, ',', '.') }}</div>
    </div>
    <div class="summary-card">
      <div class="summary-label">Tunggakan<br><span style="text-transform:none; font-weight:normal; font-size:7px; color:#9ca3af;">Outstanding</span></div>
      <div class="summary-value" style="color: #dc2626;">Rp {{ number_format($data['total_outstanding'], 0, ',', '.') }}</div>
    </div>
    <div class="summary-card highlight">
      <div class="summary-label">Rasio Pembayaran<br><span style="text-transform:none; font-weight:normal; font-size:7px; color:#4f46e5;">Collection Rate</span></div>
      <div class="summary-value">{{ number_format($data['collection_rate'], 1, ',', '.') }}%</div>
    </div>
  </div>

  <!-- Table Section -->
  <div class="section-title">Rincian Per Properti / Breakdown by Property</div>
  <table class="details-table">
    <thead>
      <tr>
        <th style="width: 40%;">Nama Properti / Property Name</th>
        <th style="text-align: right; width: 20%;">Total Tagihan / Invoiced</th>
        <th style="text-align: right; width: 20%;">Total Diterima / Collected</th>
        <th style="text-align: right; width: 20%;">Tunggakan / Outstanding</th>
      </tr>
    </thead>
    <tbody>
      @forelse($data['by_property'] as $prop)
        <tr>
          <td style="font-weight: bold; color: #1a1a2e;">{{ $prop['property_name'] }}</td>
          <td style="text-align: right; font-family: monospace;">Rp {{ number_format($prop['invoiced'], 0, ',', '.') }}</td>
          <td style="text-align: right; color: #059669; font-family: monospace;">Rp {{ number_format($prop['collected'], 0, ',', '.') }}</td>
          <td style="text-align: right; color: #dc2626; font-family: monospace;">Rp {{ number_format($prop['outstanding'], 0, ',', '.') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" style="text-align: center; color: #9ca3af; padding: 20px 0;">
            Tidak ada transaksi pada periode ini / No transaction in this period
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Footer -->
  <div class="footer">
    Laporan keuangan ini dihasilkan secara otomatis oleh sistem manajemen properti PropTrack.<br />
    This financial statement is automatically generated by the PropTrack property management system.<br />
    <strong>PropTrack Platform · Real Estate & Property Intelligence</strong>
  </div>

</div>
</body>
</html>
