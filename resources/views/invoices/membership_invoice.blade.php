<!DOCTYPE html>
<html>
<head>
    <title>Membership Invoice</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .invoice-box { max-width: 600px; padding: 20px; border: 1px solid #eee; }
        .header { font-size: 20px; font-weight: bold; margin-bottom: 20px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; border-bottom: 1px solid #ddd; }
        .footer { margin-top: 20px; font-size: 12px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">Membership Invoice</div>

        <table class="info-table">
            <tr><td><b>Name:</b></td><td>{{ $user->name }}</td></tr>
            <tr><td><b>Email:</b></td><td>{{ $user->email }}</td></tr>
            <tr><td><b>Plan:</b></td><td>{{ $plan->name }}</td></tr>
            <tr><td><b>Price:</b></td><td>₹{{ $plan->price }}</td></tr>
            <tr><td><b>Duration:</b></td><td>{{ $plan->duration }} Days</td></tr>
            <tr><td><b>Purchase Date:</b></td><td>{{ $purchase_date }}</td></tr>
            <tr><td><b>Expiry Date:</b></td><td>{{ $expiry_date }}</td></tr>
        </table>

        <div class="footer">Thank you for your purchase!</div>
    </div>
</body>
</html>
