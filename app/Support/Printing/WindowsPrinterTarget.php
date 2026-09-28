<?php

declare(strict_types=1);

namespace App\Support\Printing;

/**
 * The target string handed to escpos-php's WindowsPrintConnector.
 *
 * All printing runs on the server, so a bare queue name ("XP80") is
 * resolved against the SERVER's hostname -- every register configured that
 * way prints on (and kicks the drawer of) the server PC's printer. A
 * printer plugged into another register PC must be addressed through that
 * PC's SMB share: smb://REGISTER-2/XP80. Admins naturally type the Windows
 * UNC form (\\REGISTER-2\XP80), which the connector rejects, so it is
 * normalised here.
 */
final class WindowsPrinterTarget
{
    private const SHARE = '[\w-]+(?:\s[\w-]+)*';

    private const HOST = '(?:[\w-]+\.)*[\w-]+';

    /** Converts \\HOST\Share and //HOST/Share to smb://HOST/Share; anything else is returned trimmed. */
    public static function normalize(string $target): string
    {
        $target = trim($target);

        if (preg_match('#^(?:\\\\\\\\|//)([^\\\\/]+)[\\\\/](.+)$#', $target, $m)) {
            return 'smb://'.$m[1].'/'.str_replace('\\', '/', $m[2]);
        }

        return $target;
    }

    public static function compose(?string $host, string $share): string
    {
        $host = trim((string) $host, " \t\\/");
        $share = trim($share);

        return $host === '' ? $share : 'smb://'.$host.'/'.$share;
    }

    /** @return array{host: ?string, share: string} host is null for a queue on the server itself. */
    public static function split(string $target): array
    {
        $target = self::normalize($target);

        if (preg_match('#^smb://([^/]+)/(.+)$#i', $target, $m)) {
            return ['host' => $m[1], 'share' => $m[2]];
        }

        return ['host' => null, 'share' => $target];
    }

    /**
     * Mirrors the forms WindowsPrintConnector accepts, minus embedded
     * credentials: storing a password in the terminals table would leak it
     * to anyone with terminal-settings access. Authenticate the web server's
     * Windows account to the register PC with `cmdkey` instead.
     */
    public static function isValid(string $target): bool
    {
        $target = self::normalize($target);

        return (bool) preg_match('/^(?:LPT\d|COM\d|'.self::SHARE.')$/', $target)
            || (bool) preg_match('#^smb://'.self::HOST.'/(?:[\w-]+/)?'.self::SHARE.'$#', $target);
    }
}
