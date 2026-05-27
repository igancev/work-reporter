<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Platform;

/**
 * Cross-platform helper to resolve the current user's home directory.
 *
 * `HOME` is the POSIX convention used by Linux and macOS. On Windows the
 * canonical equivalent is `USERPROFILE`, with `HOMEDRIVE` + `HOMEPATH` as a
 * last-resort fallback when shells / containers strip `USERPROFILE`. Reading
 * `HOME` directly on Windows usually returns an empty string and silently
 * resolves `~`-prefixed config paths to the filesystem root.
 */
final class HomeDirectory
{
    public static function resolve(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $userProfile = (string) getenv('USERPROFILE');
            if ($userProfile !== '') {
                return $userProfile;
            }

            $drive = (string) getenv('HOMEDRIVE');
            $path = (string) getenv('HOMEPATH');
            if ($drive !== '' || $path !== '') {
                return $drive . $path;
            }

            return '';
        }

        return (string) getenv('HOME');
    }
}
