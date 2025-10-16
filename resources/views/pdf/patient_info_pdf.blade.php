<!DOCTYPE html>
<html>
<head>
    <title>Patient Check-Up Schedule Certificate</title>
    <style>
        @page {
            margin: 30px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #2c3e50;
            background-color: #fff;
            padding: 40px;
            margin: 0;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #154360;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            margin: 10px 0 5px;
            color: #154360;
        }

        .header p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }

        .logo {
            max-width: 90px;
            margin-bottom: 10px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 20px;
            color: #154360;
            border-left: 5px solid #154360;
            padding-left: 10px;
            margin-bottom: 15px;
        }

        .patient-details {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .patient-details div {
            flex: 1;
        }

        .patient-details p {
            margin: 5px 0;
            font-size: 14px;
        }

        .appointment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .appointment-table th,
        .appointment-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }

        .appointment-table th {
            background-color: #154360;
            color: white;
        }

        .info-box {
            background-color: #ecf0f1;
            padding: 15px 20px;
            border-left: 5px solid #1abc9c;
            margin-bottom: 15px;
            font-size: 14px;
        }

        ul {
            margin-top: 0;
            padding-left: 20px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #ccc;
            padding-top: 15px;
            margin-top: 50px;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }

        .signature-line {
            width: 45%;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 5px;
            font-size: 13px;
        }

        .validation-box {
            border-left: 5px solid #1abc9c;
            background-color: #ecf9f6;
            padding: 15px;
            font-size: 14px;
            margin-top: 30px;
        }

        .certificate-code {
            font-size: 14px;
            color: #666;
        }

        .stamp-box {
            border: 2px dashed #999;
            padding: 10px;
            width: 180px;
            text-align: center;
            font-style: italic;
            color: #777;
            margin-top: 40px;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <div class="header">
        
        <h1>PATIENT REGISTRATION CERTIFICATE</h1>
        <p class="certificate-code">
            Certificate No: {{ substr(md5(uniqid()), 0, 10) }} | Issued: {{ $generatedDate }}
        </p>
    </div>

    <div class="section">
        <div class="section-title">Patient Details</div>
        <div class="patient-details">
            <div>
                <p><strong>Full Name:</strong> {{ $patientName }}</p>
                <p><strong>Hospital Number:</strong> {{ $hospitalNumber }}</p>
                <p><strong>Date of Birth:</strong> {{ $dateOfBirth }}</p>
                <p><strong>Gender:</strong> {{ $gender }}</p>
            </div>
            <div>
                <p><strong>Contact Number:</strong> {{ $phoneNumber }}</p>
                <p><strong>Email:</strong> {{ $email }}</p>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">One-Year Check-Up Schedule (Every 28 Days)</div>
        <table class="appointment-table">
            <thead>
                <tr>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($appointmentSchedule as $appointment)
                <tr>
                    <td>{{ $appointment['date'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Check-Up Overview</div>
        <div class="info-box">
            <strong>First Check-Up:</strong> {{ $initialAppointment }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Important Notes</div>
        <ul>
            <li>This certificate confirms the official schedule of patient check-ups.</li>
            <li>You can bring this document to every appointment.</li>
            <li>Late arrivals may result in rescheduling.</li>
        </ul>
    </div>

    <div class="validation-box">
        <strong>Validation Notice:</strong> This is an official document issued by <strong>{{ config('app.name') }}</strong>.
        Any unauthorized changes invalidate this certificate. Contact the hospital records office for verification.
    </div>

    <div class="signature-section">
        <div class="signature-line">Patient’s Signature</div>
        <div class="signature-line">Authorized Hospital Representative</div>
    </div>

    <div class="footer">
        <p>This is a system-generated certificate and does not require a physical signature.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>

</body>
</html>
