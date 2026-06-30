<?php
// File: utils/CloudinaryUploader.php

class CloudinaryUploader {
    /**
     * Upload dynamic raw binary file blocks directly via secure streams
     */
    public static function upload($tmpFilePath) {
        $config = require __DIR__ . '/../config/cloudinary.php';
        
        $timestamp = time();
        $params = [
            'folder' => 'smart_media/cameras',
            'timestamp' => $timestamp
        ];
        
        // Build Signature Hash Parameters String
        ksort($params);
        $signString = "";
        foreach ($params as $key => $value) {
            $signString .= "$key=$value&";
        }
        $signString = rtrim($signString, '&') . $config['api_secret'];
        $signature = sha1($signString);

        // Core Multipart Form Data Stream Assembly Payload
        $cfile = new CURLFile($tmpFilePath);
        $postData = [
            'file' => $cfile,
            'api_key' => $config['api_key'],
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => $params['folder']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/" . $config['cloud_name'] . "/image/upload");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("Cloudinary Infrastructure Network Failure: " . $err);
        }

        $response = json_decode($result, true);
        if (isset($response['error'])) {
            throw new Exception("Cloudinary Execution Rejection: " . $response['error']['message']);
        }

        return $response['secure_url'] ?? null;
    }
}
?>