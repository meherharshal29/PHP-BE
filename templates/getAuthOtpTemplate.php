<?php
// File: templates/getAuthOtpTemplate.php

function getAuthOtpTemplate($userName, $otpCode, $isAdminAccount = false) {
    $accountContext = $isAdminAccount ? "System Administrator Terminal Access" : "Customer Portal Secure Verification";
    $warningNote = $isAdminAccount 
        ? "Security Alert: If this entry challenge was not triggered by you, change your infrastructure passwords immediately." 
        : "For security, do not share this token with anyone, including Smart Media support personnel.";

    return '
    <div style="font-family: \'Inter\', Arial, sans-serif; max-width: 480px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div style="background-color: ' . ($isAdminAccount ? '#000000' : '#4B49AC') . '; padding: 25px; text-align: center;">
            <h1 style="margin: 0; color: #ffffff; font-size: 20px; letter-spacing: 2px;">SMART MEDIA</h1>
            <span style="color: #e2e8f0; font-size: 11px; text-transform: uppercase; margin-top: 5px; display: inline-block;">' . $accountContext . '</span>
        </div>
        
        <div style="padding: 35px; text-align: center;">
            <h2 style="color: #1e293b; font-size: 22px; margin-top: 0;">Verification Code</h2>
            <p style="color: #64748b; font-size: 15px; line-height: 1.5; margin-bottom: 25px;">Hello ' . htmlspecialchars($userName) . ', use the verification code below to authorize your session window parameters.</p>
            
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 10px; display: inline-block; letter-spacing: 6px; font-size: 32px; font-weight: 800; color: #1e293b; margin: 10px auto;">
                ' . htmlspecialchars($otpCode) . '
            </div>
            
            <p style="color: #94a3b8; font-size: 12px; margin-top: 20px;">This token code expires in exactly <strong>10 minutes</strong>.</p>
            
            <hr style="border: 0; border-top: 1px solid #f1f5f9; margin: 30px 0;">
            
            <p style="margin: 0; font-size: 12px; color: ' . ($isAdminAccount ? '#991b1b' : '#64748b') . '; line-height: 1.4; font-style: italic;">
                ' . $warningNote . '
            </p>
        </div>
        
        <div style="background-color: #f1f5f9; padding: 15px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
            © 2026 Smart Media Security Center. All rights reserved.
        </div>
    </div>';
}