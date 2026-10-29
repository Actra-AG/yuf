<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use InvalidArgumentException;
use LogicException;
use Override;

/**
 * Mailbox for tests: holds the messages in memory and records what the code does, without network. Behaves like
 * `StreamImapMailbox` (the same `LogicException` without `open()` or `selectFolder()`, `ImapException` for a missing
 * folder or UID). A moved message gets the next free UID in the target folder.
 */
final class RecordingImapMailbox implements ImapMailbox
{
    /**
     * @var list<string> `open host:port`, `select <folder>`, `find <limit>`, `fetch <uid>`, `move <uid> <folder>`,
     *                   `close` in the order they happened
     */
    public private(set) array $events = [];

    private bool $isOpen = false;
    private ?string $selectedFolder = null;
    private int $lastUid;

    /**
     * @param array<string, array<int, ReceivedMail>> $folders the messages by folder name and UID, e.g.
     *                                                         `['INBOX' => [1 => $mail], 'Archive' => []]`
     * @param ImapException|null                      $openException thrown by `open()`, e.g. to test an unreachable
     *                                                               server
     */
    public function __construct(
        public private(set) array $folders = ['INBOX' => []],
        private readonly ?ImapException $openException = null,
    ) {
        $uids = [0];
        foreach ($folders as $messages) {
            $uids = [...$uids, ...array_keys(array: $messages)];
        }
        $this->lastUid = max($uids);
    }

    /**
     * @return int the UID of the added message
     */
    public function addMessage(string $folder, ReceivedMail $receivedMail): int
    {
        $this->lastUid++;
        $this->folders[$folder][$this->lastUid] = $receivedMail;

        return $this->lastUid;
    }

    #[Override]
    public function open(ImapSettings $settings): void
    {
        $this->events[] = 'open ' . $settings->hostName . ':' . $settings->port;
        if ($this->openException !== null) {
            throw $this->openException;
        }
        $this->isOpen = true;
    }

    #[Override]
    public function selectFolder(string $name): void
    {
        $this->checkOpen();
        $this->events[] = 'select ' . $name;
        $this->selectedFolder = null;
        $this->checkFolderExists(name: $name);
        $this->selectedFolder = $name;
    }

    #[Override]
    public function findUids(int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(message: 'The limit must be at least 1.');
        }
        $folder = $this->getSelectedFolder();
        $this->events[] = 'find ' . $limit;
        $uids = array_keys(array: $this->messagesOf(folder: $folder));
        sort(array: $uids);

        return array_slice(array: $uids, offset: 0, length: $limit);
    }

    #[Override]
    public function fetchMessage(int $uid): ReceivedMail
    {
        $folder = $this->getSelectedFolder();
        $this->events[] = 'fetch ' . $uid;

        return $this->getMessage(folder: $folder, uid: $uid);
    }

    #[Override]
    public function moveMessage(int $uid, string $targetFolder): void
    {
        $folder = $this->getSelectedFolder();
        $this->events[] = 'move ' . $uid . ' ' . $targetFolder;
        $receivedMail = $this->getMessage(folder: $folder, uid: $uid);
        $this->checkFolderExists(name: $targetFolder);
        unset($this->folders[$folder][$uid]);
        $this->lastUid++;
        $this->folders[$targetFolder][$this->lastUid] = $receivedMail;
    }

    #[Override]
    public function close(): void
    {
        if (!$this->isOpen) {
            return;
        }
        $this->events[] = 'close';
        $this->isOpen = false;
        $this->selectedFolder = null;
    }

    private function checkOpen(): void
    {
        if (!$this->isOpen) {
            throw new LogicException(message: 'The mailbox is not open: call open() first.');
        }
    }

    private function checkFolderExists(string $name): void
    {
        if (!array_key_exists(key: $name, array: $this->folders)) {
            throw new ImapException(message: 'The folder "' . $name . '" does not exist.');
        }
    }

    private function getSelectedFolder(): string
    {
        $this->checkOpen();

        return $this->selectedFolder ?? throw new LogicException(
            message: 'No folder is selected: call selectFolder() first.',
        );
    }

    private function getMessage(string $folder, int $uid): ReceivedMail
    {
        $messages = $this->messagesOf(folder: $folder);
        if (!array_key_exists(key: $uid, array: $messages)) {
            throw new ImapException(message: 'The folder "' . $folder . '" has no message with UID ' . $uid . '.');
        }

        return $messages[$uid];
    }

    /**
     * @return array<int, ReceivedMail>
     */
    private function messagesOf(string $folder): array
    {
        $this->checkFolderExists(name: $folder);

        return $this->folders[$folder] ?? [];
    }
}
