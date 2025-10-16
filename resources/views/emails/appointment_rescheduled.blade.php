<!DOCTYPE html>
<html>
<head>
    <title>Appointment Rescheduled</title>
</head>
<body>
    <p>Dear {{ $data['patientName'] }},</p>
    
    <p>We noticed you missed your scheduled appointment on {{ $data['missedDate'] }}.</p>
    
    <p>Your appointment has been automatically rescheduled to:</p>
    
    <p><strong>New Appointment Date:</strong> {{ $data['newDate'] }}</p>

    
    <p>Hospital Number: {{ $data['hospitalNumber'] }}</p>
    
    <p>Thank you,<br>
    DialiEase Team</p>
</body>
</html>