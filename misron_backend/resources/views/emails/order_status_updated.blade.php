<!DOCTYPE html>
<html>
<head>
    <title>{{ $isAdmin ? 'Admin Notification' : 'Order Status Updated' }}</title>
</head>
<body>
    <h1>{{ $isAdmin ? 'Admin Notification' : 'Order Status Updated' }}</h1>

    @if ($isAdmin)
        <p>Dear Admin,</p>
        <p>The order with ID: {{ $order->code }} has been updated to status: {{ $order->delivery_status }}.</p>
    @else
        <p>Dear {{ $user->name }},</p>
        <p>Your order with ID: {{ $order->code }} has been updated to status: {{ $order->delivery_status }}.</p>
        <p>Thank you for shopping with us!</p>
    @endif

    <p>Best regards,</p>
    <p>{{ get_setting('website_name') ?? ''}}</p>
</body>
</html>
