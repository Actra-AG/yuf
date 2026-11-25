<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

final class Directory
{
    /**
     * Creates the directory, race-safe: parallel requests may create the same directory at the same time, so the
     * warning of a failed mkdir() is suppressed (the ErrorHandler would turn it into an exception) and only the final
     * state counts. Callers check is_dir() first, so an existing directory costs no mkdir() call.
     *
     * @param int $permissions Mode for mkdir(), the umask applies
     *
     * @return bool Whether the directory exists afterwards
     */
    public static function create(string $path, int $permissions, bool $recursive): bool
    {
        set_error_handler(callback: static fn(): bool => true);

        try {
            $created = mkdir(directory: $path, permissions: $permissions, recursive: $recursive);
        } finally {
            restore_error_handler();
        }
        if ($created) {
            return true;
        }
        clearstatcache(clear_realpath_cache: false, filename: $path);

        return is_dir(filename: $path);
    }
}
