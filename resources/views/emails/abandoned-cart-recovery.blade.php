<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete Your Purchase</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background-color: #1a56db; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .body { padding: 30px; color: #333333; }
        .body p { line-height: 1.6; }
        .items { margin: 20px 0; }
        .item { display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eeeeee; }
        .item img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; margin-right: 15px; }
        .item-details { flex: 1; }
        .item-name { font-weight: bold; margin: 0; }
        .item-qty { color: #666; font-size: 14px; }
        .total { font-size: 20px; font-weight: bold; text-align: right; margin: 20px 0; }
        .button {
            display: inline-block;
            background-color: #1a56db;
            color: white;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px auto;
            text-align: center;
        }
        .footer { background-color: #f9f9f9; padding: 20px; text-align: center; color: #888888; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Hey {{ $order->name }}, your cart is waiting!</h1>
        </div>

        <div class="body">
            <p>We noticed you didn't complete your purchase. Your items are still reserved for you!</p>

            <div class="items">
                @foreach($order->orderItems as $item)
                    <div class="item">
                        @if($item->product && $item->product->image)
                            <img src="{{ asset('storage/' . $item->product->image) }}" alt="{{ $item->product->name ?? 'Product' }}">
                        @else
                            <div style="width:80px;height:80px;background:#eee;border-radius:6px;margin-right:15px;"></div>
                        @endif
                        <div class="item-details">
                            <p class="item-name">{{ $item->product->name ?? 'Product' }}</p>
                            <p class="item-qty">Quantity: {{ $item->quantity }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="total">
                Total: ${{ number_format($order->total, 2) }}
            </div>

            <div style="text-align:center;">
                <a href="{{ $recoveryUrl }}" class="button">Complete Your Purchase Now</a>
            </div>

            <p>This link will expire soon, so don't wait too long!</p>
            <p>If you have any questions, feel free to reply to this email.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>You are receiving this email because you started a checkout at {{ config('app.url') }}</p>
        </div>
    </div>
</body>
</html>