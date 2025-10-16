<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 20px; text-align: center; }
        .content { background: #f9fafb; padding: 20px; }
        .order-details { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Confirmed</h1>
            <p>CAPD Medical Clinic</p>
        </div>
        
        <div class="content">
            <p>Dear {{ $order['patient_name'] }},</p>
            <p>Your payment has been successfully processed. Your order is now ready for pickup.</p>
            
            <div class="order-details">
                <h2>Payment Details</h2>
                <p><strong>Order Reference:</strong> {{ $order['order_reference'] }}</p>
                <p><strong>Payment Date:</strong> {{ \Carbon\Carbon::parse($order['order_date'])->format('F d, Y') }}</p>
                <p><strong>Payment Method:</strong> {{ ucfirst($order['payment_method']) }}</p>
                <p><strong>Payment Reference:</strong> {{ $order['payment_reference'] }}</p>
                <p><strong>Amount Paid:</strong> ₱{{ number_format($order['total_amount'], 2) }}</p>
                <p><strong>Scheduled Pickup:</strong> {{ \Carbon\Carbon::parse($order['scheduled_pickup_date'])->format('F d, Y') }}</p>
            </div>
            
            <p>Your medical supplies are ready for collection during your scheduled appointment. Please bring this confirmation and valid identification.</p>
            
            <p>Thank you for choosing CAPD Medical Clinic!</p>
            
            <p>Best regards,<br>CAPD Medical Clinic Team</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} CAPD Medical Clinic. All rights reserved.</p>
        </div>
    </div>
</body>
</html>