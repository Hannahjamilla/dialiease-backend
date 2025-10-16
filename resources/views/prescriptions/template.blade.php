<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prescription - {{ $patient['name'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .hospital-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .department {
            font-size: 14px;
        }
        .patient-info {
            margin-bottom: 20px;
        }
        .prescription-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .prescription-table th, .prescription-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .prescription-table th {
            background-color: #f2f2f2;
        }
        .pd-section {
            margin-top: 20px;
        }
        .pd-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pd-table th, .pd-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
        }
        .signature {
            border-top: 1px solid #000;
            width: 300px;
            margin-left: auto;
            padding-top: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="hospital-name">NATIONAL KIDNEY AND TRANSPLANT INSTITUTE</div>
        <div class="department">Department of Adult Nephrology</div>
        <div>OPD-General Nephrology Form</div>
        <div><strong>OUT PATIENT PRESCRIPTION</strong></div>
    </div>

    <div class="patient-info">
        <table width="100%">
            <tr>
                <td width="60%"><strong>NAME (Last,First Middle):</strong> {{ $patient['name'] }}</td>
                <td width="40%"><strong>HOSPITAL #:</strong> {{ $patient['hospital_number'] }}</td>
            </tr>
            <tr>
                <td><strong>BIRTHDATE:</strong> {{ date('m/d/Y', strtotime($patient['birthdate'])) }}</td>
                <td><strong>SEX:</strong> {{ $patient['sex'] }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Date:</strong> {{ $patient['date'] }}</td>
            </tr>
        </table>
    </div>

    <table class="prescription-table">
        <thead>
            <tr>
                <th width="40%">Medication Generic</th>
                <th width="40%">Dose, Unit, Freq</th>
                <th width="20%">Dispense #</th>
            </tr>
        </thead>
        <tbody>
            @foreach($medicines as $medicine)
            <tr>
                <td>{{ strtoupper($medicine['generic_name']) }} (PO)</td>
                <td>{{ $medicine['dosage'] }} {{ $medicine['frequency'] }}</td>
                <td>
                    @php
                        // Calculate dispense quantity based on frequency and duration
                        $qty = 0;
                        if (strpos(strtolower($medicine['frequency']), 'day') !== false) {
                            preg_match('/(\d+)\s*x\s*(a\s*day|day)/i', $medicine['frequency'], $matches);
                            $timesPerDay = isset($matches[1]) ? (int)$matches[1] : 1;
                            
                            preg_match('/(\d+)\s*(day|week|month)/i', $medicine['duration'], $durationMatches);
                            if (isset($durationMatches[1]) && isset($durationMatches[2])) {
                                $duration = (int)$durationMatches[1];
                                $unit = strtolower($durationMatches[2]);
                                
                                if ($unit === 'day') {
                                    $qty = $timesPerDay * $duration;
                                } elseif ($unit === 'week') {
                                    $qty = $timesPerDay * 7 * $duration;
                                } elseif ($unit === 'month') {
                                    $qty = $timesPerDay * 30 * $duration;
                                }
                            } else {
                                // Default to 30 days if duration not parsed correctly
                                $qty = $timesPerDay * 30;
                            }
                        }
                    @endphp
                    {{ ceil($qty) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(isset($pd_data) && !empty($pd_data['system']))
    <div class="pd-section">
        <p><strong>PD system:</strong> {{ $pd_data['system'] }}</p>
        <p><strong>Total exchanges per day:</strong> {{ $pd_data['totalExchanges'] }}</p>
        
        @if(isset($pd_data['exchanges']) && count($pd_data['exchanges']) > 0)
        <table class="pd-table">
            <tr>
                <th>Dwell Time</th>
                @for($i = 1; $i <= count($pd_data['exchanges']); $i++)
                    <th>{{ $i }}{{ $i == 1 ? 'st' : ($i == 2 ? 'nd' : ($i == 3 ? 'rd' : 'th')) }}</th>
                @endfor
            </tr>
            <tr>
                <td>Hours</td>
                @foreach($pd_data['exchanges'] as $exchange)
                    <td>{{ $exchange }}</td>
                @endforeach
            </tr>
        </table>
        @endif
        
        @if(isset($pd_data['bagPercentages']) && count($pd_data['bagPercentages']) > 0)
        <p><strong>Total # of bags:</strong> 
            @foreach($pd_data['bagPercentages'] as $index => $percentage)
                {{ $percentage }}% {{ isset($pd_data['bagCounts'][$index]) ? '('.$pd_data['bagCounts'][$index].')' : '' }}
                @if(!$loop->last) + @endif
            @endforeach
        </p>
        @endif
        
        <p><strong>PD modality:</strong> {{ $pd_data['modality'] }}</p>
        <p><strong>Fill volume:</strong> {{ $pd_data['fillVolume'] }}</p>
    </div>
    @endif

    @if(!empty($additional_instructions))
    <div class="remarks">
        <p><strong>Remarks:</strong></p>
        <p>{{ $additional_instructions }}</p>
    </div>
    @endif

    <div class="footer">
        <div class="signature">
            _________________________<br>
            <strong>Doctor's Signature</strong>
        </div>
    </div>
</body>
</html>