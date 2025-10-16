<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prescription - {{ $patient['name'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .patient-info { margin-bottom: 20px; }
        .medicine-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .medicine-table th, .medicine-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .medicine-table th { background-color: #f2f2f2; }
        .pd-info { margin-top: 20px; }
        .footer { margin-top: 50px; border-top: 1px solid #333; padding-top: 20px; }
        .signature { float: right; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NATIONAL KIDNEY AND TRANSPLANT INSTITUTE</h1>
        <h2>Department of Adult Nephrology</h2>
        <h3>OPD-General Nephrology Form</h3>
        <h3>OUT PATIENT PRESCRIPTION</h3>
    </div>

    <div class="patient-info">
        <p><strong>NAME (Last,First Middle):</strong> {{ strtoupper($patient['last_name'] ?? '') }}, {{ strtoupper($patient['first_name'] ?? '') }} {{ strtoupper($patient['middle_name'] ?? '') }}</p>
        <p><strong>HOSPITAL #:</strong> {{ $patient['hospitalNumber'] ?? 'N/A' }} 
           <strong>BIRTHDATE:</strong> {{ $patient['birthdate'] ? \Carbon\Carbon::parse($patient['birthdate'])->format('m/d/Y') : 'N/A' }} 
           <strong>SEX:</strong> {{ $patient['sex'] ?? 'N/A' }} 
           <strong>Date:</strong> {{ now()->format('m/d/Y h:i:s A') }}</p>
    </div>

    <table class="medicine-table">
        <thead>
            <tr>
                <th>Medication Generic</th>
                <th>Dose, Unit, Freq</th>
                <th>Dispense #</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prescription['medicines'] as $medicine)
            <tr>
                <td>{{ $medicine['name'] }} @if($medicine['generic_name']) ({{ $medicine['generic_name'] }}) @endif</td>
                <td>{{ $medicine['dosage'] }} {{ $medicine['frequency'] }} @if($medicine['duration']) for {{ $medicine['duration'] }} @endif</td>
                <td>
                    @if($medicine['duration'] && $medicine['frequency'])
                        @php
                            // Calculate dispense quantity based on duration and frequency
                            $duration = (int) filter_var($medicine['duration'], FILTER_SANITIZE_NUMBER_INT);
                            $freqCount = 1;
                            
                            if (strpos($medicine['frequency'], '2X') !== false) $freqCount = 2;
                            elseif (strpos($medicine['frequency'], '3X') !== false) $freqCount = 3;
                            elseif (strpos($medicine['frequency'], '4X') !== false) $freqCount = 4;
                            
                            $dispenseQty = $duration * $freqCount;
                        @endphp
                        {{ $dispenseQty }}
                    @else
                        N/A
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($prescription['pd_data']))
    <div class="pd-info">
        <p><strong>PD system:</strong> {{ $prescription['pd_data']['system'] ?? 'N/A' }}</p>
        <p><strong>Total exchanges per day:</strong> {{ $prescription['pd_data']['totalExchanges'] ?? 'N/A' }}</p>
        
        @if(!empty($prescription['pd_data']['exchanges']))
        <table class="medicine-table">
            <thead>
                <tr>
                    <th>Dwell Time</th>
                    @for($i = 0; $i < count($prescription['pd_data']['exchanges']); $i++)
                        <th>{{ $i+1 }}{{ $i == 0 ? 'st' : ($i == 1 ? 'nd' : ($i == 2 ? 'rd' : 'th')) }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $prescription['pd_data']['dwellTime'] ?? 'N/A' }} hours</td>
                    @foreach($prescription['pd_data']['exchanges'] as $exchange)
                        <td>{{ $exchange }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
        @endif

        @if(!empty($prescription['pd_data']['bagPercentages']))
        <p><strong>Remarks</strong></p>
        <p><strong>Total # of bags:</strong> 
            @foreach($prescription['pd_data']['bagPercentages'] as $index => $percentage)
                {{ $percentage }}: {{ $prescription['pd_data']['bagCounts'][$index] ?? 0 }}{{ $index < count($prescription['pd_data']['bagPercentages']) - 1 ? ', ' : '' }}
            @endforeach
        </p>
        @endif

        <p><strong>PD modality:</strong> {{ $prescription['pd_data']['modality'] ?? 'N/A' }}</p>
        <p><strong>Fill volume:</strong> {{ $prescription['pd_data']['fillVolume'] ?? 'N/A' }}</p>
    </div>
    @endif

    @if(!empty($prescription['additional_instructions']))
    <div class="additional-instructions">
        <p><strong>Additional Instructions:</strong></p>
        <p>{{ $prescription['additional_instructions'] }}</p>
    </div>
    @endif

    <div class="footer">
        <div class="signature">
            <p>_________________________</p>
            <p><strong>{{ $doctor['name'] }}</strong></p>
            <p>License No: {{ $doctor['license'] }}</p>
            <p>ADULT NEPHROLOGY</p>
        </div>
    </div>
</body>
</html>