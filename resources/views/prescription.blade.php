<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Medical Prescription</title>
  <style>
    body {
      font-family: 'Arial', sans-serif;
      font-size: 14px;
      color: #000;
      background: #fff;
      margin: 0 auto;
      padding: 40px;
      max-width: 800px;
      position: relative;
    }

    .header {
      border-bottom: 1px solid #000;
      padding-bottom: 15px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
    }

    .clinic-info h1 {
      margin: 0;
      font-size: 20px;
    }

    .clinic-info p {
      margin: 3px 0;
      font-size: 13px;
    }

    .prescription-meta {
      text-align: right;
    }

    .prescription-id, .date-issued {
      font-size: 12px;
    }

    .section-title {
      font-weight: bold;
      font-size: 16px;
      margin-bottom: 8px;
      border-bottom: 1px solid #000;
      padding-bottom: 3px;
      margin-top: 30px;
    }

    .patient-info {
      border: 1px solid #000;
      padding: 15px;
      margin-bottom: 25px;
    }

    .info-grid {
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
    }

    .info-item {
      width: 48%;
      margin-bottom: 10px;
    }

    .info-label {
      font-weight: bold;
    }

    .medicines-table, .pd-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      font-size: 13px;
    }

    .medicines-table th, .medicines-table td,
    .pd-table th, .pd-table td {
      border: 1px solid #000;
      padding: 8px;
      text-align: left;
    }

    .instructions {
      border: 1px solid #000;
      padding: 15px;
      white-space: pre-wrap;
      font-size: 13px;
    }

    .footer {
      margin-top: 50px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }

    .signature-area {
      text-align: right;
    }

    .signature-line {
      margin-top: 50px;
      border-top: 1px solid #000;
      width: 200px;
    }

    .signature-text {
      font-size: 12px;
      text-align: right;
      margin-top: 5px;
    }

    .doctor-info {
      font-size: 13px;
      text-align: right;
    }

    .pd-bags ul {
      margin: 10px 0 0 20px;
      padding: 0;
      list-style: disc;
    }

    .watermark {
      position: absolute;
      opacity: 0.05;
      font-size: 100px;
      transform: rotate(-30deg);
      top: 250px;
      left: 100px;
      z-index: -1;
      color: #000;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="watermark">PRESCRIPTION</div>

  <div class="header">
    <div class="clinic-info">
      <h1>National Kidney and Transplant Institute</h1>
      <p>CAPD Unit – Peritoneal Dialysis Training and Support Center</p>
      <p>East Avenue, Quezon City, Philippines</p>
      <p>Tel: (02) 8928-0601 | www.nkti.gov.ph</p>
    </div>
    <div class="prescription-meta">
      <div class="prescription-id">RX-{{ strtoupper(substr(md5(time()), 0, 8)) }}</div>
      <div class="date-issued">{{ $date }}</div>
    </div>
  </div>

  <div class="patient-info">
    <div class="section-title">PATIENT INFORMATION</div>
    <div class="info-grid">
      <div class="info-item">
        <p><span class="info-label">Full Name:</span> {{ $patient['name'] }}</p>
      </div>
      <div class="info-item">
        @if(isset($patient['hospitalNumber']))
        <p><span class="info-label">Hospital No.:</span> {{ $patient['hospitalNumber'] }}</p>
        @endif
        <p><span class="info-label">Date Issued:</span> {{ $date }}</p>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">PRESCRIBED MEDICATIONS</div>
    <table class="medicines-table">
      <thead>
        <tr>
          <th style="width: 5%;">No.</th>
          <th style="width: 30%;">Medicine</th>
          <th style="width: 20%;">Dosage</th>
          <th style="width: 20%;">Frequency</th>
          <th style="width: 25%;">Duration</th>
        </tr>
      </thead>
      <tbody>
        @foreach($medicines as $index => $medicine)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td>{{ $medicine['name'] }}</td>
          <td>{{ $medicine['dosage'] }}</td>
          <td>{{ $medicine['frequency'] }}</td>
          <td>{{ $medicine['duration'] }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($pd_data && ($pd_data['bags'] || $pd_data['first'] || $pd_data['second'] || $pd_data['third']))
  <div class="section">
    <div class="section-title">PD SOLUTION INFORMATION</div>
    <div class="info-grid">
      <div class="info-item">
        <p><span class="info-label">System:</span> {{ $pd_data['system'] ?? '-' }}</p>
        <p><span class="info-label">Total Exchanges:</span> {{ $pd_data['totalExchanges'] ?? '-' }}</p>
        <p><span class="info-label">Dwell Time:</span> {{ $pd_data['dwellTime'] ?? '-' }} hours</p>
      </div>
    </div>

    <table class="pd-table">
      <thead>
        <tr>
          <th>Dwell Time</th>
          <th>1st</th>
          <th>2nd</th>
          <th>3rd</th>
          @if(isset($pd_data['fourth']))<th>4th</th>@endif
          @if(isset($pd_data['fifth']))<th>5th</th>@endif
          @if(isset($pd_data['sixth']))<th>6th</th>@endif
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ $pd_data['dwellTime'] ?? '___' }} hrs</td>
          <td>{{ $pd_data['first'] ?? '-' }}</td>
          <td>{{ $pd_data['second'] ?? '-' }}</td>
          <td>{{ $pd_data['third'] ?? '-' }}</td>
          @if(isset($pd_data['fourth']))<td>{{ $pd_data['fourth'] }}</td>@endif
          @if(isset($pd_data['fifth']))<td>{{ $pd_data['fifth'] }}</td>@endif
          @if(isset($pd_data['sixth']))<td>{{ $pd_data['sixth'] }}</td>@endif
        </tr>
      </tbody>
    </table>

    @if(isset($pd_data['bags']) && count($pd_data['bags']) > 0)
    <div class="pd-bags">
      <strong>Solutions:</strong>
      <ul>
        @foreach($pd_data['bags'] as $bag)
        <li>{{ $bag['percentage'] ? $bag['percentage'].'% - ' : '' }}{{ $bag['count'] }} bags</li>
        @endforeach
      </ul>
    </div>
    @endif
  </div>
  @endif

  @if($additional_instructions)
  <div class="section">
    <div class="section-title">ADDITIONAL INSTRUCTIONS</div>
    <div class="instructions">
      {{ $additional_instructions }}
    </div>
  </div>
  @endif

  <div class="footer">
    <div style="font-size: 12px;">
      <p>Valid until: {{ now()->addDays(30)->format('F j, Y') }}</p>
    </div>
    <div class="signature-area">
      <div class="doctor-info">
        <div><strong>{{ $doctor['name'] }}</strong></div>
        <div>{{ $doctor['specialization'] ?? 'General Physician' }}</div>
      </div>
      <div class="signature-line"></div>
      <div class="signature-text">Physician's Signature</div>
    </div>
  </div>
</body>
</html>
