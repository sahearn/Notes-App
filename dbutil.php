<?php

$mysqli = new mysqli("mysql.db.url", "db.user", "db.pass", "db.name");
if ($mysqli->connect_errno) {
    printf("Connect failed: %s\n", $mysqli->connect_error);
    exit();
}
// make sure all timestamps are inserted UTC - format to local on output
$mysqli->query("SET time_zone = '+00:00'");

function decrypt_note($encrypted) {
    $decoded = base64_decode($encrypted);

    // Split nonce and ciphertext back apart
    $nonce      = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $key = hex2bin(trim(file_get_contents('/location/of/enckey.txt')));

    // Decrypt — returns false if the message was tampered with
    $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

    if ($plaintext === false) {
        throw new Exception("Decryption failed — message may have been tampered with!");
    }

    return $plaintext;
}

function encrypt_note($plaintext) {
    // Generate a random nonce (must be unique per message)
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $key = hex2bin(trim(file_get_contents('/location/of/enckey.txt')));

    // Encrypt the message
    $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

    // Encode for storage/transmission (nonce must travel with ciphertext)
    $encoded = base64_encode($nonce . $ciphertext);

    return $encoded;
}

?>