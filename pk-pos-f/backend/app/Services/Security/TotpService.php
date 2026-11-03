<?php

namespace App\Services\Security;

class TotpService
{
    /**
     * Generate standard Base32 secret string for TOTP.
     */
    public function generateBase32Secret(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Verify a 6-digit TOTP code against a secret with time-slice drift tolerance.
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = trim($code);
        $currentTimeSlice = (int) floor(time() / 30);

        for ($drift = -$discrepancy; $drift <= $discrepancy; $drift++) {
            if ($this->calculateTotp($secret, $currentTimeSlice + $drift) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate TOTP code for a time slice using standard Base32 decoding and HMAC-SHA1 (RFC 6238).
     */
    public function calculateTotp(string $secret, int $timeSlice): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binaryString = '';
        $secret = strtoupper($secret);
        for ($i = 0; $i < strlen($secret); $i++) {
            $val = strpos($alphabet, $secret[$i]);
            if ($val !== false) {
                $binaryString .= sprintf('%05b', $val);
            }
        }

        $secretBytes = '';
        $binaryChunks = str_split($binaryString, 8);
        foreach ($binaryChunks as $chunk) {
            if (strlen($chunk) === 8) {
                $secretBytes .= chr(bindec($chunk));
            }
        }

        $timeBytes = pack('J', $timeSlice);
        $hmac = hash_hmac('sha1', $timeBytes, $secretBytes, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;

        return sprintf('%06d', $value % 1000000);
    }

    /**
     * Generate set of random 8-character recovery codes.
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }
}

