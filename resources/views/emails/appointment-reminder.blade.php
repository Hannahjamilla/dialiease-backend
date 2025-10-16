<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Appointment Reminder</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #374151;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9fafb;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .urgent-badge {
            background-color: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            margin-top: 10px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .content {
            padding: 30px;
        }
        .appointment-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #3b82f6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .appointment-date {
            font-size: 20px;
            font-weight: 700;
            color: #1e3a8a;
            margin: 0;
        }
        .appointment-time {
            font-size: 16px;
            color: #6b7280;
            margin: 5px 0 0 0;
        }
        .highlight-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .reminder-text {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 15px 0;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .contact-info {
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .icon {
            display: inline-block;
            width: 20px;
            text-align: center;
            margin-right: 8px;
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🩺 Appointment Reminder</h1>
            @if($isToday)
            <div class="urgent-badge">⏰ TODAY'S APPOINTMENT</div>
            @endif
        </div>
        
        <div class="content">
            <p>Dear <strong>{{ $patientName }}</strong>,</p>
            
            <p>This is a friendly reminder about your upcoming medical appointment:</p>
            
            <div class="appointment-card">
                <h3 class="appointment-date">
                    📅 {{ $appointmentDate }}
                    @if($isToday)
                    <span style="color: #ef4444;">- TODAY</span>
                    @endif
                </h3>
               
            </div>

            @if($isToday)
            <div class="highlight-box">
                <strong>🚨 Important Notice:</strong> Your appointment is scheduled for <strong>today</strong>. 
                Please confirm your attendance as soon as possible or contact us if you need to reschedule.
            </div>
            @endif

            <div class="reminder-text">
                <p>🌟 <strong>Friendly Reminder:</strong> Don't forget to:</p>
                <ul style="text-align: left; margin: 15px 0;">
           
                    <li>Prepare any relevant medical records or test results</li>
                    <li>Take any regular medications as scheduled</li>
                    <li>Stay hydrated and have a light meal before your visit</li>
                </ul>
                <p><em>Travel safely! Ingat sa biyahe!</em></p>
        
    </div>
</body>
</html>