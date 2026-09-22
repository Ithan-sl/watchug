<?php
// includes/security.php
// Standalone file for secure encryption/decryption of proxy URLs

declare(strict_types=1);

if (!function_exists('get_proxy_secret')) {
    function get_proxy_secret(): string {
        // First try environment variable
        $secret = getenv('PROXY_SECRET_KEY');
        if ($secret) return $secret;
        
        if (isset($_ENV['PROXY_SECRET_KEY'])) return $_ENV['PROXY_SECRET_KEY'];
        
        // Try reading config/.env directly if it exists
        $envFile = __DIR__ . '/../config/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2 && trim((string)$parts[0]) === 'PROXY_SECRET_KEY') {
                    return trim($parts[1]);
                }
            }
        }
        
        // Static fallback key to guarantee decryption across multiple servers without configuration
        return 'errofatal412fontscloud4567tawshostatu54caralhodope';
    }
}

if (!function_exists('encrypt_proxy_url')) {
    function encrypt_proxy_url(string $url, $expiry): string {
        $expiry = (int)$expiry;
        $secret = get_proxy_secret();
        $plain = $url . '|' . $expiry;
        
        // Standard AES-128-CBC encryption (built-in in PHP OpenSSL)
        $method = 'AES-128-CBC';
        $ivLen = openssl_cipher_iv_length($method);
        
        // Deterministic IV based on URL, secret key, and expiry.
        // This ensures the same URL encrypted in the same 4-hour block has the exact same token
        // to maximize Cloudflare Edge caching and save VPS proxy bandwidth.
        $iv = substr(hash('sha256', $url . $secret . $expiry, true), 0, $ivLen);
        
        $encrypted = openssl_encrypt($plain, $method, $secret, OPENSSL_RAW_DATA, $iv);
        
        // Pack IV and encrypted data together
        $packed = $iv . $encrypted;
        
        // Return a URL-safe base64 encoded string
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($packed));
    }
}

if (!function_exists('decrypt_proxy_url')) {
    function decrypt_proxy_url(string $encryptedStr): ?array {
        $secret = get_proxy_secret();
        
        // Restore standard base64 characters
        $data = base64_decode(str_replace(['-', '_'], ['+', '/'], $encryptedStr));
        if ($data === false) return null;
        
        $method = 'AES-128-CBC';
        $ivLen = openssl_cipher_iv_length($method);
        if (strlen($data) <= $ivLen) return null;
        
        $iv = substr($data, 0, $ivLen);
        $encrypted = substr($data, $ivLen);
        
        $plain = openssl_decrypt($encrypted, $method, $secret, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) return null;
        
        $parts = explode('|', $plain);
        if (count($parts) < 2) return null;
        
        $expiry = (int)array_pop($parts);
        $url = implode('|', $parts); // Re-join if the URL itself contained '|'
        
        return [
            'url' => $url,
            'expiry' => $expiry
        ];
    }
}

if (!class_exists('Punycode')) {
    class Punycode {
        private static $base = 36;
        private static $tmin = 1;
        private static $tmax = 26;
        private static $skew = 38;
        private static $damp = 700;
        private static $initial_bias = 72;
        private static $initial_n = 128;
        private static $prefix = 'xn--';
        private static $delimiter = '-';

        public static function encode($input) {
            $parts = explode('.', $input);
            foreach ($parts as &$part) {
                $part = self::encodePart($part);
            }
            return implode('.', $parts);
        }

        private static function encodePart($input) {
            if (preg_match('/^[a-z0-9\-]*$/i', $input)) {
                return $input;
            }

            $codepoints = self::utf8ToCodepoints($input);
            $n = self::$initial_n;
            $delta = 0;
            $bias = self::$initial_bias;
            $output = '';

            $basicCodepoints = [];
            foreach ($codepoints as $cp) {
                if ($cp < 128) {
                    $basicCodepoints[] = $cp;
                    $output .= chr($cp);
                }
            }

            $h = $b = count($basicCodepoints);
            if ($b > 0) {
                $output .= self::$delimiter;
            }

            while ($h < count($codepoints)) {
                $m = 2147483647;
                foreach ($codepoints as $cp) {
                    if ($cp >= $n && $cp < $m) {
                        $m = $cp;
                    }
                }

                $delta += ($m - $n) * ($h + 1);
                $n = $m;

                foreach ($codepoints as $cp) {
                    if ($cp < $n) {
                        $delta++;
                    }

                    if ($cp == $n) {
                        $q = $delta;
                        for ($k = self::$base; ; $k += self::$base) {
                            $t = $k <= $bias ? self::$tmin : ($k >= $bias + self::$tmax ? self::$tmax : $k - $bias);
                            if ($q < $t) break;
                            $code = $t + (($q - $t) % (self::$base - $t));
                            $output .= self::encodeDigit($code);
                            $q = (int)(($q - $t) / (self::$base - $t));
                        }

                        $output .= self::encodeDigit($q);
                        $bias = self::adapt($delta, $h + 1, $h == $b);
                        $delta = 0;
                        $h++;
                    }
                }

                $delta++;
                $n++;
            }

            return self::$prefix . $output;
        }

        private static function utf8ToCodepoints($str) {
            $codepoints = [];
            $len = strlen($str);
            $i = 0;
            while ($i < $len) {
                $c = ord($str[$i++]);
                if ($c < 0x80) {
                    $codepoints[] = $c;
                } elseif ($c < 0xE0) {
                    $codepoints[] = (($c & 0x1F) << 6) | (ord($str[$i++]) & 0x3F);
                } elseif ($c < 0xF0) {
                    $codepoints[] = (($c & 0x0F) << 12) | ((ord($str[$i++]) & 0x3F) << 6) | (ord($str[$i++]) & 0x3F);
                } else {
                    $codepoints[] = (($c & 0x07) << 18) | ((ord($str[$i++]) & 0x3F) << 12) | ((ord($str[$i++]) & 0x3F) << 6) | (ord($str[$i++]) & 0x3F);
                }
            }
            return $codepoints;
        }

        private static function encodeDigit($d) {
            return chr($d + ($d < 26 ? 97 : 22));
        }

        private static function adapt($delta, $numpoints, $firsttime) {
            $delta = $firsttime ? (int)($delta / self::$damp) : (int)($delta / 2);
            $delta += (int)($delta / $numpoints);
            $k = 0;
            while ($delta > (int)(((self::$base - self::$tmin) * self::$tmax) / 2)) {
                $delta = (int)($delta / (self::$base - self::$tmin));
                $k += self::$base;
            }
            return $k + (int)((((self::$base - self::$tmin) + 1) * $delta) / ($delta + self::$skew));
        }
    }
}

if (!function_exists('safe_idn_to_ascii')) {
    function safe_idn_to_ascii(string $url): string {
        if (empty($url)) return $url;
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['host'])) {
            $host = $parsed['host'];
            if (preg_match('/[^\x00-\x7F]/', $host)) {
                if (function_exists('idn_to_ascii')) {
                    $asciiHost = false;
                    if (defined('INTL_IDNA_2008')) {
                        $asciiHost = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_2008);
                    }
                    if ($asciiHost === false) {
                        $asciiHost = idn_to_ascii($host);
                    }
                    if ($asciiHost !== false) {
                        $pos = strpos($url, $host);
                        if ($pos !== false) {
                            $url = substr_replace($url, $asciiHost, $pos, strlen($host));
                        }
                    }
                } else {
                    $asciiHost = Punycode::encode($host);
                    if ($asciiHost !== false) {
                        $pos = strpos($url, $host);
                        if ($pos !== false) {
                            $url = substr_replace($url, $asciiHost, $pos, strlen($host));
                        }
                    }
                }
            }
        }
        return $url;
    }
}
