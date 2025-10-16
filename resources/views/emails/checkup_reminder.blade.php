<!DOCTYPE html>
<html>
<head>
    <title>Check-up Reminder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            color: #2c5282;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Check-up Reminder</h1>
        </div>
        
        <div class="content">
            <p>Dear {{ $patientName }},</p>
            
            @if($reminderType === 'advance')
                <p>This is a friendly reminder that you have a check-up scheduled in <strong>2 days</strong> on <strong>{{ $appointmentDate }}</strong>.</p>
            @elseif($reminderType === 'tomorrow')
                <p>This is a reminder that <strong>your check-up is tomorrow</strong> ({{ $appointmentDate }}).</p>
            @else
                <p>This is a reminder that <strong>today is your check-up day</strong> ({{ $appointmentDate }}).</p>
            @endif
            
            <p>Please prepare all necessary documents and requirements for your appointment.</p>
            <p>If you need to reschedule or have any questions, please contact our clinic.</p>
            
            <p>Best regards,<br>Your Healthcare Team</p>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply directly to this email.</p>
            <p>Patient ID: {{ $hospitalNumber }}</p>
        </div>
    </div>
</body>
</html>