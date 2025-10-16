<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4f46e5; color: white; padding: 20px; text-align: center; }
        .content { background: #f9fafb; padding: 20px; }
        .order-details { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
        .button { background: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
            <p>CAPD Medical Clinic</p>
        </div>
        
        <div class="content">
            <p>Dear {{ $order['patient_name'] }},</p>
            <p>Thank you for your order at CAPD Medical Clinic. Your order has been confirmed and is being processed.</p>
            
            <div class="order-details">
                <h2>Order Details</h2>
                <p><strong>Order Reference:</strong> {{ $order['order_reference'] }}</p>
                <p><strong>Order Date:</strong> {{ \Carbon\Carbon::parse($order['order_date'])->format('F d, Y') }}</p>
                <p><strong>Scheduled Pickup:</strong> {{ \Carbon\Carbon::parse($order['scheduled_pickup_date'])->format('F d, Y') }}</p>
                <p><strong>Payment Method:</strong> {{ ucfirst($order['payment_method']) }}</p>
                
                <h3>Order Items:</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f3f4f6;">
                            <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb;">Item</th>
                            <th style="padding: 10px; text-align: center; border-bottom: 1px solid #e5e7eb;">Qty</th>
                            <th style="padding: 10px; text-align: right; border-bottom: 1px solid #e5e7eb;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order['items'] as $item)
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #e5e7eb;">{{ $item['name'] }}</td>
                            <td style="padding: 10px; text-align: center; border-bottom: 1px solid #e5e7eb;">{{ $item['quantity'] }}</td>
                            <td style="padding: 10px; text-align: right; border-bottom: 1px solid #e5e7eb;">₱{{ number_format($item['total_price'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; text-align: right;">
                    <p><strong>Subtotal: ₱{{ number_format($order['subtotal'], 2) }}</strong></p>
                    @if($order['discount_amount'] > 0)
                    <p><strong>Discount: -₱{{ number_format($order['discount_amount'], 2) }}</strong></p>
                    @endif
                    <p><strong style="font-size: 1.2em;">Total: ₱{{ number_format($order['total_amount'], 2) }}</strong></p>
                </div>
            </div>
            
            <p>Please bring valid identification when picking up your medical supplies during your scheduled appointment.</p>
            
            <p>If you have any questions, please contact our clinic.</p>
            
            <p>Best regards,<br>CAPD Medical Clinic Team</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} CAPD Medical Clinic. All rights reserved.</p>
        </div>
    </div>
</body>
</html>