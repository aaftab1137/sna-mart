<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boosting Invoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        .invoice-box {
            max-width: 700px;
            margin: auto;
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        }
        h2 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f4f4f4;
        }
        .text-right {
            text-align: right;
        }
        .total {
            font-size: 16px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="invoice-box">
        <h2>Boosting Invoice</h2>

        <p><strong>Invoice Date:</strong> {{ $purchase_date }}</p>
        <p><strong>Invoice No:</strong> #{{ $boosting->id }}</p>

        <hr>

        <h4>Customer Details</h4>
        <p><strong>Name:</strong> {{ $user->name }}</p>
        <p><strong>Email:</strong> {{ $user->email }}</p>

        <hr>

        <h4>Boosting Details</h4>
        <table>
            <tr>
                <th>Product</th>
                <td>{{ $product->title }}</td>
            </tr>
            <tr>
                <th>Views Per Day</th>
                <td>{{ $plan->views_per_day }}</td>
            </tr>
            <tr>
                <th>Boosting Duration</th>
                <td>{{ $days }} Days ({{ $purchase_date }} to {{ $expiry_date }})</td>
            </tr>
            <tr class="total">
                <th>Total Price</th>
                <td>₹{{ number_format($total_price, 2) }}</td>
            </tr>
        </table>

        <hr>
        <p style="text-align: center;">Thank you for using our services!</p>
    </div>

</body>
</html>
