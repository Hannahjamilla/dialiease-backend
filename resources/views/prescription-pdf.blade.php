<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prescription</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 15px;
            color: #000;
            font-size: 12px;
            line-height: 1.2;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .institute-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .department-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .patient-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .patient-details {
            flex: 2;
        }
        .prescription-date {
            flex: 1;
            text-align: right;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
        }
        .info-value {
            flex: 1;
        }
        .medication-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .medication-table th {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .medication-table td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: top;
        }
        .pd-section {
            margin-bottom: 15px;
        }
        .pd-row {
            display: flex;
            margin-bottom: 5px;
        }
        .pd-label {
            font-weight: bold;
            width: 180px;
        }
        .pd-value {
            flex: 1;
        }
        .dwell-time-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .dwell-time-table th, .dwell-time-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }
        .dwell-time-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .remarks-section {
            margin-bottom: 15px;
        }
        .remarks-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .signature-section {
            margin-top: 30px;
            text-align: right;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin-left: auto;
            margin-bottom: 5px;
        }
        .doctor-info {
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="institute-name">NATIONAL KIDNEY AND TRANSPLANT INSTITUTE</div>
        <div class="department-name">Department of Adult Nephrology</div>
        <div class="form-name">OPD-General Nephrology Form</div>
        <div class="form-name">OUT PATIENT PRESCRIPTION</div>
    </div>

    <div class="patient-info">
        <div class="patient-details">
            <div class="info-row">
                <span class="info-label">NAME (Last,First Middle)</span>
                <span class="info-value">{{ strtoupper($patient->user->last_name) }}, {{ strtoupper($patient->user->first_name) }}  {{ strtoupper($patient->user->middle_name) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">HOSPITAL #</span>
                <span class="info-value">{{ $patient->hospitalNumber }}</span>
                <span class="info-label">BIRTHDATE:</span>
                <span class="info-value">{{ $patient->user->date_of_birth ? \Carbon\Carbon::parse($patient->user->date_of_birth)->format('m/d/Y') : 'N/A' }}</span>
                <span class="info-label">SEX:</span>
                <span class="info-value">{{ $patient->user->gender ? strtoupper(substr($patient->user->gender, 0, 1)) : 'N/A' }}</span>
            </div>
        </div>
        <div class="prescription-date">
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value">{{ $date }} {{ $time }}</span>
            </div>
        </div>
    </div>

    <table class="medication-table">
        <thead>
            <tr>
                <th style="width: 40%;">Medication Generic</th>
                <th style="width: 35%;">Dose, Unit, Freq</th>
                <th style="width: 25%;">Dispense #</th>
            </tr>
        </thead>
        <tbody>
            @foreach($medicines as $medicine)
            <tr>
                <td>{{ $medicine->medicine->name }} @if($medicine->medicine->generic_name)({{ $medicine->medicine->generic_name }})@endif</td>
                <td>{{ $medicine->dosage }} {{ $medicine->frequency }}</td>
                <td>
                    @php
                        // Extract numeric value from duration for dispense count
                        $duration = $medicine->duration;
                        $durationNumber = 30; // Default value
                        
                        if (preg_match('/(\d+)/', $duration, $matches)) {
                            $durationNumber = (int)$matches[1];
                        }
                        
                        // Calculate dispense count based on frequency and duration
                        $frequency = strtolower($medicine->frequency);
                        $multiplier = 1;
                        
                        if (strpos($frequency, '3x') !== false || strpos($frequency, 'three times') !== false) {
                            $multiplier = 3;
                        } elseif (strpos($frequency, '2x') !== false || strpos($frequency, 'twice') !== false) {
                            $multiplier = 2;
                        } elseif (strpos($frequency, 'every day') !== false || strpos($frequency, 'daily') !== false) {
                            $multiplier = 1;
                        }
                        
                        $dispenseCount = $durationNumber * $multiplier;
                    @endphp
                    {{ $dispenseCount }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($pdData['system']))
    <div class="pd-section">
        <div class="pd-row">
            <span class="pd-label">PD system:</span>
            <span class="pd-value">{{ $pdData['system'] ?? 'N/A' }}</span>
        </div>
        <div class="pd-row">
            <span class="pd-label">PD modality:</span>
            <span class="pd-value">{{ $pdData['modality'] ?? 'N/A' }}</span>
        </div>
        <div class="pd-row">
            <span class="pd-label">Total exchanges per day:</span>
            <span class="pd-value">{{ $pdData['totalExchanges'] ?? 'N/A' }}</span>
        </div>
        <div class="pd-row">
            <span class="pd-label">Fill volume:</span>
            <span class="pd-value">{{ $pdData['fillVolume'] ?? 'N/A' }}</span>
        </div>
        
        @if(!empty($pdData['exchanges']))
        <div style="margin-top: 10px;">
            <div style="font-weight: bold; margin-bottom: 5px;">Dwell Time</div>
            <table class="dwell-time-table">
                <tr>
                    <th></th>
                    <th>1st</th>
                    <th>2nd</th>
                    <th>3rd</th>
                    <th>4th</th>
                    <th>5th</th>
                    <th>6th</th>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Hours</td>
                    @for($i = 0; $i < 6; $i++)
                    <td>{{ $pdData['exchanges'][$i] ?? '' }}</td>
                    @endfor
                </tr>
            </table>
        </div>
        @endif
        
        <div class="remarks-section">
            <div class="remarks-label">Remarks</div>
            <div>
                Total # of bags: 
                @if(!empty($pdData['bagPercentages']))
                    {{ implode(', ', array_filter($pdData['bagPercentages'])) }}
                @else
                    N/A
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="signature-section">
        <div class="signature-line"></div>
        <div class="doctor-info">
            {{ $doctor->first_name }} {{ $doctor->last_name }}<br>
            ADULT NEPHROLOGY<br>
            License No: {{ $doctor->Doc_license ?? 'Not specified' }}
        </div>
    </div>
</body>
</html>