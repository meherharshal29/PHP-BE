<?php
// File: templates/getOrderConfirmationTemplate.php

function getOrderConfirmationTemplate($userName, $orderItems, $grandTotal, $isSystemAdmin = false) {
    $title = $isSystemAdmin ? "New Rental Logistics Alert" : "Reservation Confirmed!";
    $subTitle = $isSystemAdmin 
        ? "Administrator, user " . htmlspecialchars($userName) . " has placed a new order. Please prepare the gear for dispatch." 
        : "Hi " . htmlspecialchars($userName) . ", your professional gear has been reserved. Our team is preparing it for delivery.";

    $itemsRows = '';
    foreach ($orderItems as $item) {
        $itemsRows .= '
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; color: #1e293b;">
                <strong>' . htmlspecialchars($item['name']) . '</strong><br>
                <span style="font-size: 12px; color: #94a3b8;">Qty: ' . (int)$item['qty'] . ' | ' . (int)$item['days'] . ' Days</span>
            </td>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; text-align: right; color: #1e293b; font-weight: bold;">
                ₹' . number_format($item['itemTotal'], 2) . '
            </td>
        </tr>';
    }

    $verificationMessage = $isSystemAdmin 
        ? "Check profile identity fields inside your core administrative terminal system before formal handoffs." 
        : "Please keep your original government structural documentation layout card handy for system validation processes during transit checkups.";

    return '
    <div style="font-family: \'Inter\', Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background-color: #ffffff;">
        <div style="background-color: #4B49AC; padding: 25px; text-align: center;">
            <h1 style="margin: 0; color: #ffffff; font-size: 24px; letter-spacing: 1px;">SMART MEDIA</h1>
        </div>
        <div style="padding: 30px;">
            <h2 style="color: #1e293b; margin-top: 0;">' . $title . '</h2>
            <p style="color: #64748b; line-height: 1.6;">' . $subTitle . '</p>
            
            <div style="margin: 25px 0; border-top: 2px solid #f1f5f9; padding-top: 20px;">
                <h3 style="color: #4B49AC; font-size: 14px; text-transform: uppercase;">Rental Summary</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; color: #94a3b8; font-size: 12px; padding-bottom: 10px;">ITEM</th>
                            <th style="text-align: right; color: #94a3b8; font-size: 12px; padding-bottom: 10px;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $itemsRows . '
                    </tbody>
                </table>
            </div>

            <div style="background-color: #f8fafc; padding: 20px; border-radius: 8px; text-align: right;">
                <span style="font-size: 14px; color: #64748b;">Grand Total (Tax Included):</span>
                <strong style="font-size: 22px; color: #4B49AC; display: block; margin-top: 5px;">₹' . number_format($grandTotal, 2) . '</strong>
            </div>

            <div style="margin-top: 30px; padding: 15px; border-left: 4px solid #4B49AC; background-color: #eff6ff;">
                <p style="margin: 0; font-size: 13px; color: #1e40af;">
                    <strong>Verification Check:</strong> ' . $verificationMessage . '
                </p>
            </div>
        </div>
        <div style="background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8;">
            Sent by Smart Media Logistics Engine. <br> This is an automated email, please do not reply.
        </div>
    </div>';
}