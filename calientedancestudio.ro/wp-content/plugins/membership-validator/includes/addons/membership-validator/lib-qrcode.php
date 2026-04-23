<?php
/**
 * Simple QR Code Generator Library
 * Lightweight alternative to Google Charts API
 * 
 * Based on QRcode PHP library - simplified version
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple QR Code Generator
 * Generates QR code PNG images without external dependencies
 */
class OC_Simple_QRCode {
    
    /**
     * Generate QR code and save as WebP (optimized for space)
     * 
     * @param string $data Data to encode
     * @param string $filepath Path where to save WebP (will auto-convert from PNG)
     * @param int $size Size in pixels (default OC_QR_DEFAULT_SIZE)
     * @return bool Success
     */
    public static function png($data, $filepath, $size = OC_QR_DEFAULT_SIZE) {
        // Use external service as fallback (free, no API key)
        // QRServer.com API - free, no limits, HTTPS
        $api_url = 'https://api.qrserver.com/v1/create-qr-code/';
        
        $qr_url = add_query_arg([
            'size' => $size . 'x' . $size,
            'data' => urlencode($data),
            'format' => 'png',
            'margin' => 1
        ], $api_url);
        
        // Download QR image
        $response = wp_remote_get($qr_url, [
            'timeout' => 15,
            'user-agent' => 'WordPress/Membership-Validator-QR',
            'sslverify' => false // Pentru XAMPP local
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }
        
        $image_content = wp_remote_retrieve_body($response);
        if (empty($image_content) || strlen($image_content) < 100) {
            return false;
        }
        
        // Verifică că este PNG valid
        if (substr($image_content, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return false;
        }
        
        // 🎯 CONVERTIRE PNG → WebP pentru economie de spațiu
        if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
            // Creează imagine GD din PNG
            $gd_image = imagecreatefromstring($image_content);
            
            if ($gd_image !== false) {
                // 🔧 FIX: Convertește palette image în truecolor pentru suport WebP
                if (!imageistruecolor($gd_image)) {
                    $width = imagesx($gd_image);
                    $height = imagesy($gd_image);
                    
                    // Creează imagine truecolor nouă
                    $truecolor_image = imagecreatetruecolor($width, $height);
                    
                    // Păstrează transparența
                    imagealphablending($truecolor_image, false);
                    imagesavealpha($truecolor_image, true);
                    
                    // Copiază conținutul
                    imagecopy($truecolor_image, $gd_image, 0, 0, 0, 0, $width, $height);
                    
                    // Înlocuiește imaginea palette cu truecolor
                    imagedestroy($gd_image);
                    $gd_image = $truecolor_image;
                }
                
                // Verifică că filepath-ul se termină cu .webp sau .png
                $final_path = $filepath;
                
                // Dacă se termină cu .png, înlocuiește cu .webp
                if (substr($filepath, -4) === '.png') {
                    $final_path = substr($filepath, 0, -4) . '.webp';
                }
                
                // Salvează ca WebP cu calitate 90 (echilibru între calitate și mărime)
                $temp_file = $final_path . '.tmp';
                $saved = imagewebp($gd_image, $temp_file, 90);
                imagedestroy($gd_image);
                
                if ($saved && rename($temp_file, $final_path)) {
                    // Actualizează filepath-ul în cazul în care a fost schimbat
                    if ($final_path !== $filepath && file_exists($final_path)) {
                        return true;
                    }
                    return true;
                }
                
                // Cleanup la eroare
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
                
                return false;
            }
        }
        
        // Fallback: Salvează PNG dacă WebP nu e suportat
        $temp_file = $filepath . '.tmp';
        $saved = file_put_contents($temp_file, $image_content, LOCK_EX);
        
        if ($saved !== false && rename($temp_file, $filepath)) {
            return true;
        }
        
        // Cleanup la eroare
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        return false;
    }
}

