<?php
// File: templates/getOrderEmailTemplate.php

function getOrderEmailTemplate($customerName, $items, $grandTotal, $status, $isAdmin = false, $shippingAddress, $adharNumber, $paymentMethod) {
    $clientUrl = 'http://localhost:4200';
    $statusUpper = strtoupper($status);
    
    $statusColors = [
        'pending'   => '#FFA500',
        'confirmed' => '#28a745',
        'shipped'   => '#007bff',
        'returned'  => '#6c757d',
        'cancelled' => '#dc3545'
    ];
    $statusColor = $statusColors[strtolower($status)] ?? '#000000';

    $headerTitle = $isAdmin ? "🚨 NEW RENTAL BOOKING ALERT" : "Your Camera Rental Order is {$statusUpper}";

    if ($isAdmin) {
        $mainMessage = '<p style="font-size:16px;">A new rental booking has been placed by <strong>' . htmlspecialchars($customerName) . '</strong>. Please review and confirm/ship the order soon.</p>';
        $footerMessage = '<p style="font-size:14px;color:#555;">Log in to your admin panel to manage this order.</p>';
    } else {
        $mainMessage = '<p style="font-size:16px;">Dear <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
                        <p style="font-size:16px;">Thank you for choosing Smart Media! Your rental order has been updated to <strong style="color:' . $statusColor . ';">' . $statusUpper . '</strong>.</p>';
        $footerMessage = '<p style="font-size:14px;color:#555;">If you have any questions, feel free to contact us.</p>';
    }

    $itemsRows = '';
    foreach ($items as $item) {
        $itemTotal = $item['itemTotal'] ?? ($item['qty'] * $item['days'] * 200);
        $orderLink = $isAdmin ? "{$clientUrl}/admin/orders/{$item['orderId']}" : "{$clientUrl}/orders/{$item['orderId']}";
        $daysLabel = $item['days'] > 1 ? 'days' : 'day';

        $itemsRows .= '
        <tr>
            <td style="padding:12px; border-bottom:1px solid #eee; text-align:left;"><a href="' . $orderLink . '" style="color:#007bff; text-decoration:none;">#' . htmlspecialchars($item['orderId']) . '</a></td>
            <td style="padding:12px; border-bottom:1px solid #eee; text-align:left;">
                <img src="' . htmlspecialchars($item['image']) . '" alt="' . htmlspecialchars($item['name']) . '" style="width:80px; height:80px; object-fit:cover; border-radius:8px; margin-right:12px; vertical-align:middle;" />
                <strong>' . htmlspecialchars($item['name']) . '</strong>
            </td>
            <td style="padding:12px; border-bottom:1px solid #eee; text-align:center;">' . (int)$item['qty'] . '</td>
            <td style="padding:12px; border-bottom:1px solid #eee; text-align:center;">' . (int)$item['days'] . ' ' . $daysLabel . '</td>
            <td style="padding:12px; border-bottom:1px solid #eee; text-align:center;">' . htmlspecialchars($item['startDate']) . ' - ' . htmlspecialchars($item['endDate']) . '</td>
            <td style="padding:12px; border-bottom:1px solid #eee; text-align:right;">₹' . number_format($itemTotal, 2) . '</td>
        </tr>';
    }

    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($headerTitle) . '</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:0; }
        .container { max-width:600px; margin:0 auto; background:#ffffff; }
    </style>
</head>
<body style="background:#f4f4f4; margin:0; padding:20px 0;">
    <center>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="container" style="border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.1); width: 100%;">
            <tr>
                <td style="background:#1a1a1a; color:#ffffff; padding:30px; text-align:center;">
                    <h1 style="margin:0; font-size:24px;">Smart Media Camera Rentals</h1>
                    <p style="margin:10px 0 0; font-size:18px;">' . htmlspecialchars($headerTitle) . '</p>
                </td>
            </tr>
            <tr>
                <td style="padding:30px;">
                    ' . $mainMessage . '
                    <h2 style="font-size:20px; margin:30px 0 15px;">Order Summary</h2>
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="padding:12px; text-align:left;">Order ID</th>
                                <th style="padding:12px; text-align:left;">Item</th>
                                <th style="padding:12px; text-align:center;">Qty</th>
                                <th style="padding:12px; text-align:center;">Days</th>
                                <th style="padding:12px; text-align:center;">Rental Period</th>
                                <th style="padding:12px; text-align:right;">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $itemsRows . '
                            <tr>
                                <td colspan="5" style="padding:15px; text-align:right; font-weight:bold;">Grand Total:</td>
                                <td style="padding:15px; text-align:right; font-weight:bold; font-size:18px; color:#28a745;">₹' . number_format($grandTotal, 2) . '</td>
                            </tr>
                        </tbody>
                    </table>

                    <h2 style="font-size:20px; margin:30px 0 15px;">Additional Details</h2>
                    <p style="font-size:14px; margin:10px 0;">Shipping Address: ' . htmlspecialchars($shippingAddress) . '</p>
                    <p style="font-size:14px; margin:10px 0;">Verification Status: [Aadhaar Redacted]</p>
                    <p style="font-size:14px; margin:10px 0;">Payment Method: ' . htmlspecialchars(strtoupper($paymentMethod)) . '</p>
                    
                    ' . $footerMessage . '
                    
                    <p style="margin-top:30px; font-size:14px; color:#888; text-align:center;">
                        © 2026 Smart Media. All rights reserved.<br>
                        <a href="' . $clientUrl . '" style="color:#007bff;">Visit Website</a> | Contact: meherharshal924@gmail.com
                    </p>
                </td>
            </tr>
            <tr>
                <td style="background:#1a1a1a; color:#aaaaaa; padding:20px; text-align:center; font-size:12px;">
                    This is an automated email. Please do not reply directly.
                </td>
            </tr>
        </table>
    </center>
</body>
</html>';
}