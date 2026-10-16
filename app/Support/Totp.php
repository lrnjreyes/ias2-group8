<?php

namespace App\Support;

class Totp
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random Base32 secret key.
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $max = strlen(self::BASE32_CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $max)];
        }

        return $secret;
    }

    /**
     * Calculate the 6-digit TOTP code for a given timestamp.
     */
    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = (int) floor($timestamp / 30);

        return self::getCodeForTimeSlice($secret, $timeSlice);
    }

    /**
     * Verify a 6-digit TOTP code allowing for clock drift.
     */
    public static function verify(string $secret, string $code, int $discrepancy = 1, ?int $timestamp = null): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $timestamp = $timestamp ?? time();
        $currentTimeSlice = (int) floor($timestamp / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCodeForTimeSlice($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the code for a specific 30-second time slice counter.
     */
    private static function getCodeForTimeSlice(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode($secret);
        if ($secretKey === '') {
            return '000000';
        }

        // 64-bit big-endian binary representation of the counter
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);

        // Dynamic truncation (RFC 4226 / RFC 6238)
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Build the standard otpauth URI compatible with Google Authenticator and Microsoft Authenticator.
     */
    public static function getOtpAuthUri(string $issuer, string $accountName, string $secret): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Decode a Base32 encoded string into binary bytes.
     */
    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);

        if ($b32 === '') {
            return '';
        }

        $lookup = [];
        for ($i = 0; $i < 32; $i++) {
            $lookup[self::BASE32_CHARS[$i]] = $i;
        }

        $binary = '';
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $binary .= str_pad(decbin($lookup[$b32[$i]]), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        $binaryLen = strlen($binary);
        for ($i = 0; $i + 8 <= $binaryLen; $i += 8) {
            $bytes .= chr(bindec(substr($binary, $i, 8)));
        }

        return $bytes;
    }
}
