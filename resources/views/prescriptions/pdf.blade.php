<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prescription - {{ $patient->user->first_name }} {{ $patient->user->last_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.4; color: #000; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 16px; }
        .header h2 { margin: 5px 0; font-size: 14px; }
        .patient-info { margin: 15px 0; padding: 10px; background: #f5f5f5; }
        .medicine-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .medicine-table th, .medicine-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .medicine-table th { background: #ddd; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #000; font-size: 10px; text-align: center; }
        .signature { margin-top: 50px; }
        .instructions { margin: 15px 0; padding: 10px; background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NATIONAL KIDNEY AND TRANSPLANT INSTITUTE</h1>
        <h2>Department of Adult Nephrology</h2>
        <h3>OUT PATIENT PRESCRIPTION</h3>
    </div>

    <div class="patient-info">
        <table width="100%">
            <tr>
                <td width="50%"><strong>Patient:</strong> {{ $patient->user->first_name }} {{ $patient->user->middle_name ?? '' }} {{ $patient->user->last_name }}</td>
                <td><strong>Hospital #:</strong> {{ $patient->hospitalNumber ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Birthdate:</strong> {{ $patient->user->date_of_birth ?? 'N/A' }}</td>
                <td><strong>Sex:</strong> {{ $patient->user->gender ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Date:</strong> {{ $date }}</td>
                <td><strong>Time:</strong> {{ $time }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Prescribing Doctor:</strong> Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}</td>
            </tr>
        </table>
    </div>

    @if(!empty($medicines) && count($medicines) > 0)
    <h3>Medications Prescribed:</h3>
    <table class="medicine-table">
        <thead>
            <tr>
                <th width="25%">Medication</th>
                <th width="15%">Dosage</th>
                <th width="15%">Frequency</th>
                <th width="15%">Duration</th>
                <th width="30%">Instructions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($medicines as $medicine)
            <tr>
                <td>{{ $medicine->medicine->name ?? 'Unknown Medicine' }}</td>
                <td>{{ $medicine->dosage }}</td>
                <td>{{ $medicine->frequency }}</td>
                <td>{{ $medicine->duration }}</td>
                <td>{{ $medicine->instructions ?? 'As directed' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($prescription->additional_instructions)
    <div class="instructions">
        <h3>Additional Instructions:</h3>
        <p>{{ $prescription->additional_instructions }}</p>
    </div>
    @endif

    @if($prescription->pd_system)
    <div class="instructions">
        <h3>Peritoneal Dialysis Prescription:</h3>
        <table width="100%">
            <tr>
                <td width="30%"><strong>System:</strong> {{ $prescription->pd_system }}</td>
                <td width="30%"><strong>Modality:</strong> {{ $prescription->pd_modality }}</td>
                <td width="40%"><strong>Total Exchanges:</strong> {{ $prescription->pd_total_exchanges }}</td>
            </tr>
            @if($prescription->pd_fill_volume)
            <tr>
                <td><strong>Fill Volume:</strong> {{ $prescription->pd_fill_volume }} mL</td>
                <td><strong>Dwell Time:</strong> {{ $prescription->pd_dwell_time ?? 'N/A' }} hours</td>
                <td></td>
            </tr>
            @endif
            @if($prescription->pd_exchanges)
            <tr>
                <td colspan="3"><strong>Exchanges:</strong> {{ $prescription->pd_exchanges }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    <div class="signature">
        <table width="100%">
            <tr>
                <td width="60%">
                    <strong>Doctor's Signature:</strong><br><br>
                    _________________________<br>
                    Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}<br>
                    {{ $doctor->specialization ?? 'Nephrologist' }}<br>
                    <!-- License: {{ $doctor->Doc_license ?? 'N/A' }} -->
                </td>
                <td width="40%">
                    <strong>Date Issued:</strong><br><br>
                    _________________________<br>
                    {{ $date }}
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>NATIONAL KIDNEY AND TRANSPLANT INSTITUTE - Department of Adult Nephrology</p>
        <p>This prescription was electronically generated and is valid without signature.</p>
    </div>
</body>
</html>