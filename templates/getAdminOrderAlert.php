<?php
// File: templates/getAdminOrderAlert.php

function getAdminOrderAlert($userName, $orderItems, $grandTotal, $checkoutDetails) {
    $itemsRows = '';
    foreach ($orderItems as $item) {
        $itemsRows .= '
        <tr>
            <td style="padding: 15px 10px; border-bottom: 1px solid #edf2f7;">
                <div style="font-weight: bold; color: #2d3748;">' . htmlspecialchars($item['name']) . '</div>
                <div style="font-size: 12px; color: #718096;">Qty: ' . (int)$item['qty'] . ' | Days: ' . (int)$item['days'] . '</div>
            </td>
            <td style="padding: 15px 10px; border-bottom: 1px solid #edf2f7; text-align: right; font-weight: bold; color: #2d3748;">
                ₹' . number_format($item['itemTotal'], 2) . '
            </td>
        </tr>';
    }

    return '
    <div style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background-color: #ffffff;">
        <div style="background-color: #000000; padding: 25px; text-align: center;">
            <h1 style="margin: 0; color: #ffffff; font-size: 20px; letter-spacing: 2px; text-transform: uppercase;">Smart Media Logistics</h1>
        </div>

        <div style="width: 100%; height: 250px; overflow: hidden;">
            <img src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&q=80&w=1000" 
                 alt="Equipment Alert" style="width: 100%; height: 100%; object-fit: cover;">
        </div>

        <div style="padding: 30px;">
            <h2 style="color: #1a202c; margin-top: 0; font-size: 22px;">New Rental Booking Alert</h2>
            <p style="color: #4a5568; line-height: 1.6;">User <strong>' . htmlspecialchars($userName) . '</strong> has just placed a new rental order. Please verify documentation and prepare for dispatch.</p>
            
            <div style="background-color: #f7fafc; border: 1px dashed #cbd5e0; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px; text-transform: uppercase;">Customer Verification</h4>
                <p style="margin: 0; font-size: 13px; color: #4a5568;">
                    <strong>Verification Token:</strong> [Aadhaar Redacted]<br>
                    <strong>Location:</strong> ' . htmlspecialchars($checkoutDetails['city']) . '<br>
                    <strong>Address:</strong> ' . htmlspecialchars($checkoutDetails['address']) . '
                </p>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead>
                    <tr>
                        <th style="text-align: left; border-bottom: 2px solid #edf2f7; padding: 10px; font-size: 12px; color: #a0aec0;">EQUIPMENT</th>
                        <th style="text-align: right; border-bottom: 2px solid #edf2f7; padding: 10px; font-size: 12px; color: #a0aec0;">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $itemsRows . '
                </tbody>
            </table>

            <div style="margin-top: 20px; text-align: right;">
                <span style="color: #718096; font-size: 14px;">Total Amount Due:</span>
                <div style="font-size: 24px; color: #000; font-weight: 900; margin-top: 5px;">₹' . number_format($grandTotal, 2) . '</div>
            </div>

            <div style="text-align: center; margin-top: 40px;">
                <a href="http://localhost:4200/admin/dashboard" 
                   style="background-color: #000000; color: #ffffff; padding: 16px 32px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 14px;">
                    MANAGE DISPATCH
                </a>
            </div>
        </div>

        <div style="background-color: #1a202c; padding: 20px; text-align: center; font-size: 11px; color: #a0aec0;">
            SMART MEDIA PRODUCTIONS<br>
            Mumbai | Nagpur | Online<br>
            © 2026 All Rights Reserved
        </div>
    </div>';
}