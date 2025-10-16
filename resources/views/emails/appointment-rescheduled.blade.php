<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Appointment Rescheduled</title>
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
            background-color: #3f51b5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 0 0 5px 5px;
            border: 1px solid #ddd;
            border-top: none;
        }
        .appointment-details {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Appointment Rescheduled</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $firstName }} {{ $lastName }},</p>
        
        <p>Your appointment has been rescheduled. Below are the details:</p>
        
        <div class="appointment-details">
            <p><strong>Original Date:</strong> {{ \Carbon\Carbon::parse($oldDate)->format('F j, Y') }}</p>
            <p><strong>New Date:</strong> {{ \Carbon\Carbon::parse($newDate)->format('F j, Y') }}</p>
        </div>
        
        <p>Please make note of your new appointment date. If you have any questions or need to reschedule, please contact our office.</p>
        
        <p>Thank you,<br>Healthcare Team</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
    </div>
</body>
</html>