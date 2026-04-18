<?php
/**
 * AES-256-CBC Encryption Helper
 * 
 * Provides symmetric encryption/decryption for sensitive data
 * like chat messages and feedback content stored in the database.
 * 
 * The encryption key is loaded from the ENCRYPTION_KEY environment variable.
 * If not set, a default key is used (for development only).
 */

define('ENCRYPTION_METHOD', 'aes-256-cbc');

/**
 * Get the encryption key (32 bytes for AES-256).
 */
function getEncryptionKey()
{
    $key = getenv('ENCRYPTION_KEY');
    if (!$key) {
        // Fallback for development — MUST set ENCRYPTION_KEY in production .env
        $key = 'DMU_COST_SHARE_ENC_KEY_2026_SECURE';
    }
    // Ensure exactly 32 bytes for AES-256
    return substr(hash('sha256', $key, true), 0, 32);
}

/**
 * Encrypt a plaintext string.
 * Returns base64-encoded string containing IV + ciphertext.
 * Returns original text if encryption fails.
 */
function encryptData($plaintext)
{
    if (empty($plaintext)) return $plaintext;

    $key = getEncryptionKey();
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    
    $encrypted = openssl_encrypt($plaintext, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    
    if ($encrypted === false) {
        return $plaintext; // Fallback: return original if encryption fails
    }

    // Combine IV + encrypted data and base64 encode
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt an encrypted string.
 * Expects base64-encoded string containing IV + ciphertext.
 * Returns original text if decryption fails (handles legacy plaintext gracefully).
 */
function decryptData($encryptedText)
{
    if (empty($encryptedText)) return $encryptedText;

    // Try to decode base64 — if it fails, it's likely plaintext (legacy data)
    $decoded = base64_decode($encryptedText, true);
    if ($decoded === false) {
        return $encryptedText; // Not base64 = legacy plaintext
    }

    $key = getEncryptionKey();
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);

    // Check if decoded data is long enough to contain IV + ciphertext
    if (strlen($decoded) < $ivLength) {
        return $encryptedText; // Too short = legacy plaintext
    }

    $iv = substr($decoded, 0, $ivLength);
    $ciphertext = substr($decoded, $ivLength);

    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);

    if ($decrypted === false) {
        return $encryptedText; // Decryption failed = legacy plaintext
    }

    return $decrypted;
}
?>
