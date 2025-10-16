<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Reserved</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f59e0b; color: white; padding: 20px; text-align: center; }
        .content { background: #f9fafb; padding: 20px; }
        .order-details { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Reserved</h1>
            <p>CAPD Medical Clinic</p>
        </div>
        
        <div class="content">
            <p>Dear {{ $order['patient_name'] }},</p>
            <p>Your medical supplies have been reserved and will be available for pickup during your scheduled appointment.</p>
            
            <div class="order-details">
                <h2>Reservation Details</h2>
                <p><strong>Order Reference:</strong> {{ $order['order_reference'] }}</p>
                <p><strong>Reservation Date:</strong> {{ \Carbon\Carbon::parse($order['order_date'])->format('F d, Y') }}</p>
                <p><strong>Scheduled Pickup:</strong> {{ \Carbon\Carbon::parse($order['scheduled_pickup_date'])->format('F d, Y') }}</p>
                <p><strong>Total Amount:</strong> ₱{{ number_format($order['total_amount'], 2) }}</p>
                
                <h3>Reserved Items:</h3>
                <ul>
                    @foreach($order['items'] as $item)
                    <li>{{ $item['name'] }} (Quantity: {{ $item['quantity'] }})</li>
                    @endforeach
                </ul>
            </div>
            
            <p><strong>Important:</strong> Payment will be collected when you pickup your medical supplies. Please bring valid identification and be prepared to settle the amount of ₱{{ number_format($order['total_amount'], 2) }}.</p>
            
            <p>If you need to modify or cancel your reservation, please contact us at least 24 hours before your scheduled pickup.</p>
            
            <p>Best regards,<br>CAPD Medical Clinic Team</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} CAPD Medical Clinic. All rights reserved.</p>
        </div>
    </div>
</body>
</html>