<?php
// MultiPOS/config/support.php

/**
 * Konfigurasi Call Center / Support
 * - Gunakan format internasional TANPA tanda +, spasi, atau strip
 *   contoh Indonesia: 6281234567890
 */
const SUPPORT_WA_NUMBER = '6281234567890';

/**
 * Nama aplikasi (opsional) untuk dimasukkan ke pesan WA.
 */
const SUPPORT_APP_NAME = 'MultiPOS';

/**
 * Template pesan default untuk WA.
 * Akan di-URL-encode saat dipakai.
 */
function support_default_message(?string $email = null): string
{
    $emailText = $email ? $email : '(belum diisi)';
    return "Halo Call Center " . SUPPORT_APP_NAME . ", saya lupa kata sandi.\n\nEmail login: {$emailText}\nMohon bantu reset password saya. Terima kasih.";
}

/**
 * Build link WhatsApp dengan pesan terprefill.
 */
function support_whatsapp_link(?string $email = null): string
{
    $msg = support_default_message($email);
    $encoded = rawurlencode($msg);
    return "https://wa.me/" . SUPPORT_WA_NUMBER . "?text=" . $encoded;
}
