<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use InvalidArgumentException;

/**
 * A login that hosts share (`SharedLogin`): the user and the ID of the shared login, which the project stores with the
 * login in the session of every host (to end it everywhere with `SharedLogin::end()`).
 */
final readonly class SharedLoginUser
{
    /**
     * @param string $subject The ID of the user, the same on the login host and on the sites
     * @param string $sharedLoginId The ID of the shared login (`SharedLogin::createSharedLoginId()`)
     *
     * @throws InvalidArgumentException if the subject is empty or the shared login ID has another format
     */
    public function __construct(
        public string $subject,
        public string $sharedLoginId,
    ) {
        if ($subject === '') {
            throw new InvalidArgumentException(message: 'The subject must not be empty.');
        }
        if (!LoginHandOverStore::isId(id: $sharedLoginId)) {
            throw new InvalidArgumentException(message: 'Invalid shared login ID.');
        }
    }
}
