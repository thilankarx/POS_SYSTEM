<?php

declare(strict_types=1);

namespace App\Support\Printing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A `host[:port]` target for a network (ESC/POS raw-socket) printer
 * connector. PrintConnectorFactory / KitchenPrinterConnectorFactory /
 * LabelPrinterConnectorFactory all pass this straight to
 * NetworkPrintConnector with no validation at all -- an admin (or anyone
 * with terminal/location-settings access) could point it at loopback or a
 * cloud metadata endpoint (169.254.169.254) and the app server would open a
 * socket there on the next print, writing attacker-influenced bytes and
 * leaking connect-vs-timeout timing back through the resulting error.
 *
 * Deliberately does NOT reject ordinary private LAN ranges
 * (192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12) -- that is exactly where a
 * real receipt printer lives. Only loopback and link-local (which covers
 * the common cloud metadata IPs) are refused.
 */
final class ValidNetworkPrinterTarget implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        [$host, $port] = $this->splitHostPort($value, $fail);

        if ($host === null) {
            // splitHostPort() already called $fail().
            return;
        }

        if ($port !== null && (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535)) {
            $fail('The :attribute port must be between 1 and 65535.');

            return;
        }

        // "localhost" never matches FILTER_VALIDATE_IP but resolves straight
        // to loopback -- block it by name rather than relying on the caller
        // to have typed 127.0.0.1.
        if (strtolower($host) === 'localhost') {
            $fail('The :attribute must not point at a loopback or link-local address.');

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isLoopbackOrLinkLocal($host)) {
                $fail('The :attribute must not point at a loopback or link-local address.');
            }

            return;
        }

        // FILTER_VALIDATE_IP only accepts canonical dotted-quad IPv4. The
        // socket layer (fsockopen -> the C library's numeric-host parser)
        // also accepts BSD inet_aton-style shorthand ("127.1", "0177.1" for
        // octal, "0x7f000001" for hex, a bare decimal integer, ...) and
        // resolves every one of them to a real IPv4 address -- "127.1"
        // resolves to 127.0.0.1 exactly like a browser's address bar would
        // parse it. Rejecting only the canonical form let those through as
        // an unrecognised "hostname". Parse the same way inet_aton does and
        // check the canonical result, not the caller's spelling of it.
        $canonicalIp = $this->parseShorthandIpv4($host);

        if ($canonicalIp !== null) {
            if ($this->isLoopbackOrLinkLocal($canonicalIp)) {
                $fail('The :attribute must not point at a loopback or link-local address.');
            }

            return;
        }

        if (! preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,62})?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,62})?)*$/', $host)) {
            $fail('The :attribute must be a valid hostname or IP address.');
        }
    }

    /**
     * @return array{0: ?string, 1: ?string} [host, port] -- host is null if
     *                                        $fail() was already called.
     */
    private function splitHostPort(string $value, Closure $fail): array
    {
        // Bracketed IPv6, optionally with a port: "[::1]" or "[::1]:9100".
        if (str_starts_with($value, '[')) {
            if (! preg_match('/^\[(?P<host>[^\]]+)\](:(?P<port>\d*))?$/', $value, $m)) {
                $fail('The :attribute must be a valid hostname or IP address.');

                return [null, null];
            }

            return [$m['host'], ($m['port'] ?? '') !== '' ? $m['port'] : null];
        }

        // A bare address with more than one colon is unbracketed IPv6
        // ("::1", "fe80::1") -- there is no unambiguous way to split a port
        // off that, so treat the whole value as the host, no port.
        if (substr_count($value, ':') > 1) {
            return [$value, null];
        }

        return str_contains($value, ':') ? explode(':', $value, 2) : [$value, null];
    }

    /**
     * Parses BSD inet_aton-style shorthand IPv4 ("a", "a.b", "a.b.c",
     * "a.b.c.d", each part decimal/octal/hex) into canonical dotted-quad
     * form, the same way the C library's numeric-host parser (which
     * fsockopen ultimately uses) does. Returns null if $host is not
     * numeric-shorthand-shaped at all (i.e. a real hostname).
     */
    private function parseShorthandIpv4(string $host): ?string
    {
        $parts = explode('.', $host);

        if (count($parts) < 1 || count($parts) > 4) {
            return null;
        }

        $values = [];

        foreach ($parts as $part) {
            if ($part === '' || ! preg_match('/^(0[xX][0-9a-fA-F]+|0[0-7]*|[1-9][0-9]*)$/', $part)) {
                return null;
            }

            $values[] = (int) (str_starts_with($part, '0x') || str_starts_with($part, '0X')
                ? hexdec($part)
                : (str_starts_with($part, '0') && strlen($part) > 1 ? octdec($part) : $part));
        }

        $count = count($values);
        $max = match ($count) {
            1 => 4294967295,
            2 => 16777215,
            3 => 65535,
            4 => 255,
        };

        if ($values[$count - 1] > $max) {
            return null;
        }

        foreach (array_slice($values, 0, -1) as $leading) {
            if ($leading > 255) {
                return null;
            }
        }

        $address = match ($count) {
            1 => $values[0],
            2 => ($values[0] << 24) | $values[1],
            3 => ($values[0] << 24) | ($values[1] << 16) | $values[2],
            4 => ($values[0] << 24) | ($values[1] << 16) | ($values[2] << 8) | $values[3],
        };

        return implode('.', [
            ($address >> 24) & 0xFF,
            ($address >> 16) & 0xFF,
            ($address >> 8) & 0xFF,
            $address & 0xFF,
        ]);
    }

    private function isLoopbackOrLinkLocal(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = explode('.', $ip);

            // 127.0.0.0/8 (loopback), 169.254.0.0/16 (link-local, which is
            // where every major cloud's instance-metadata endpoint lives).
            return $octets[0] === '127' || ($octets[0] === '169' && $octets[1] === '254');
        }

        // ::1 (loopback), fe80::/10 (link-local).
        $normalized = strtolower($ip);

        return $normalized === '::1' || str_starts_with($normalized, 'fe80:');
    }
}
