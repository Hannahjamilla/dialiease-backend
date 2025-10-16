{{-- resources/views/emails/checkup-completed.blade.php --}}

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CAPD Checkup Completed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9fafb;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #395886;
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 25px;
        }
        .congratulations {
            background-color: #f0f9ff;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .highlight {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        .footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .signature {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }
        .reminder {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CAPD Medical Center</h1>
        </div>
        
        <div class="content">
            <h2>Checkup Completed Successfully</h2>
            
            <p>Dear {{ $patientName }},</p>
            
            <div class="congratulations">
                <p><strong>Good job! Your checkup is done and you already have received your Solution Bag.</strong></p>
            </div>
            
            <p>We're pleased to inform you that your recent CAPD checkup has been completed successfully.</p>
            
            <div class="highlight">
                <p><strong>Checkup Date:</strong> {{ $completedDate }}</p>
                <p><strong>Next Appointment:</strong> {{ $nextAppointmentDate }}</p>
            </div>
            
            @if(!empty($prescriptionDetails))
            <h3>PD Solutions Details:</h3> {{-- Fixed typo from "SOlutions" to "Solutions" --}}
            <ul>
                @if(!empty($prescriptionDetails['pd_bag_counts']))
                    <li><strong>PD Bag Counts:</strong> {{ $prescriptionDetails['pd_bag_counts'] }}</li>
                @endif
                @if(!empty($prescriptionDetails['pd_bag_percentages']))
                    <li><strong>PD Bag Percentages:</strong> {{ $prescriptionDetails['pd_bag_percentages'] }}</li>
                @endif
                @if(!empty($prescriptionDetails['additional_instructions']))
                    <li><strong>Additional Instructions:</strong> {{ $prescriptionDetails['additional_instructions'] }}</li>
                @endif
            </ul>
            @endif
            
            <div class="reminder">
                <p><strong>Ingat sa pag-uwi!</strong> Please take care on your way home.</p>
            </div>
            
            <p>Please remember to bring your medical records and any current medications to your next appointment.</p>
            
            <p>If you need to reschedule or have any questions, please contact our office at least 24 hours in advance.</p>
            
            <div class="signature">
                <p>See you next month!</p>
                <p>Best regards,<br>
                <strong>CAPD Medical Team</strong></p>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>© {{ date('Y') }} CAPD Medical Center. All rights reserved.</p>
        </div>
    </div>
</body>
</html>