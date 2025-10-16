<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .header h1 {
            color: #1a5276;
        }
        .content {
            margin: 20px 0;
        }
        .credentials {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .credentials h3 {
            margin-top: 0;
            color: #1a5276;
        }
        .info-row {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .appointment-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #eaf2f8;
            border-radius: 5px;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .attachment-note {
            margin-top: 20px;
            padding: 10px;
            background-color: #f0f0f0;
            border-left: 4px solid #1a5276;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to DialiEase</h1>
        <p>Your patient account has been created</p>
    </div>
    
    <div class="content">
        <p>Dear {{ $patientName }},</p>
        
        <p>Your account has been created by the CAPD staff. Below are your login credentials and appointment details:</p>
        
        <div class="credentials">
            <h3>Your Login Credentials</h3>
            <div class="info-row">
                <span class="info-label">Patient Name:</span>
                <span>{{ $patientName }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span>{{ $email }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Temporary Password:</span>
                <span>{{ $password }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Hospital Number:</span>
                <span>{{ $hospitalNumber }}</span>
            </div>
        </div>
        
        <div class="appointment-info">
            <h3>Your Appointment Details</h3>
            <div class="info-row">
                <span class="info-label">Initial Appointment:</span>
                <span>{{ \Carbon\Carbon::parse($initialAppointment)->format('F j, Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Follow-up Appointment:</span>
                <span>{{ \Carbon\Carbon::parse($followUpDate)->format('F j, Y') }}</span>
            </div>
            
            <p>You will receive reminder emails 3 days and 1 day before each appointment.</p>
        </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} Healthcare System. All rights reserved.</p>
    </div>
</body>
</html>
</html>