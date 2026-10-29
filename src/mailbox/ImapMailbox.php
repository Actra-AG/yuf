<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * Reads and moves the messages of one folder of a mailbox. Open, select a folder, work, close:
 * `open()` → `selectFolder()` → `findUids()` / `fetchMessage()` / `moveMessage()` → `close()`.
 */
interface ImapMailbox
{
    /**
     * Connects (always encrypted) and logs in.
     *
     * @throws ImapException if the server cannot be reached, offers no TLS or rejects the login
     */
    public function open(ImapSettings $settings): void;

    /**
     * @param string $name e.g. `INBOX` or `INBOX.Archive` (the hierarchy separator of the server), UTF-8
     *
     * @throws ImapException if the folder does not exist
     */
    public function selectFolder(string $name): void;

    /**
     * @return list<int> the UIDs of the messages in the selected folder, oldest first, at most `$limit`; messages
     *                   marked as deleted are skipped
     *
     * @throws ImapException
     */
    public function findUids(int $limit): array;

    /**
     * Reads the whole message without marking it as seen.
     *
     * @throws ImapException if the folder has no message with this UID
     */
    public function fetchMessage(int $uid): ReceivedMail;

    /**
     * Moves the message into the folder (which must exist): `UID MOVE` (RFC 6851), on servers without it `UID COPY`,
     * `\Deleted` and `UID EXPUNGE` (RFC 4315).
     *
     * @throws ImapException if the message or the folder does not exist
     */
    public function moveMessage(int $uid, string $targetFolder): void;

    /**
     * Logs out and closes the connection; does nothing if it is closed.
     */
    public function close(): void;
}
