# Reading a mailbox

`StreamImapMailbox` reads and moves the messages of one folder of an IMAP mailbox, e.g. in a cron job that processes
notification mails. It needs no `ext-imap` (PHP 8.5 does not bundle it) and does nothing else: no sending, no flags, no
folder management.

## Connection

`ImapSettings` holds the access; `encryption:` (`ImapEncryptionEnum`) sets how the connection is encrypted:

- `IMPLICIT_TLS` (default, port 993): encrypted from the first byte (IMAPS).
- `STARTTLS` (port 143): the connection is encrypted with STARTTLS before the credentials are sent. A server without
  STARTTLS, data sent before the handshake, or a failed handshake aborts with an `ImapException`.

There is no unencrypted connection. The certificate chain and the host name are always verified, TLS 1.2 or newer is
required. `port:` defaults to the port of the encryption, `timeoutSeconds:` (default 10) applies to the connection and
to every answer of the server. The login uses `AUTHENTICATE PLAIN` if the server offers it, otherwise `LOGIN`.
The password never appears in exception messages or `var_dump()`.

```php
// .env.php
'mailbox.hostname' => 'mail.example.com',
'mailbox.username' => 'notifications@example.com',
'mailbox.password' => '…',
'mailbox.encryption' => 'implicit_tls', // 'starttls'
```

```php
$environmentSettings = $core->environmentSettings;
$settings = new ImapSettings(
    hostName: $environmentSettings->getString(key: 'mailbox.hostname'),
    userName: $environmentSettings->getString(key: 'mailbox.username'),
    password: $environmentSettings->getString(key: 'mailbox.password'),
    encryption: $environmentSettings->getEnum(key: 'mailbox.encryption', enumClass: ImapEncryptionEnum::class),
);
```

## Reading and moving messages

```php
$mailbox = new StreamImapMailbox(); // type the dependency as ImapMailbox
$mailbox->open(settings: $settings);
try {
    $mailbox->selectFolder(name: 'INBOX');
    foreach ($mailbox->findUids(limit: 100) as $uid) {
        $mail = $mailbox->fetchMessage(uid: $uid);
        // … process $mail
        $mailbox->moveMessage(uid: $uid, targetFolder: 'INBOX.Processed');
    }
} finally {
    $mailbox->close();
}
```

- `findUids()` returns the UIDs of the selected folder, oldest first, at most `$limit`; messages marked as deleted are
  skipped. Process a large folder in several runs.
- `fetchMessage()` reads the whole message without marking it as seen (`BODY.PEEK[]`). Messages larger than 64 MB are
  refused.
- `moveMessage()` uses `UID MOVE` (RFC 6851). Servers without `MOVE` need `UIDPLUS`: the message is copied, marked as
  deleted and removed with `UID EXPUNGE`, so other messages marked as deleted stay untouched. The target folder must
  exist. Folder names are UTF-8 with the hierarchy separator of the server (Dovecot: `INBOX.Processed` or
  `INBOX/Processed`).
- A missing folder or UID, a rejected login or a dropped connection throws an `ImapException`; its `serverResponse`
  holds the line of the server. Calls before `open()` or `selectFolder()` throw a `LogicException`.

## Received mail

`ReceivedMail` is created by `MimeMessageParser` (also usable alone, e.g. for stored `.eml` files). All texts are UTF-8;
a malformed message never throws, what cannot be read is `null` or empty.

| Property      | Content                                                                                           |
|:--------------|:--------------------------------------------------------------------------------------------------|
| `messageId`   | header `Message-ID` as sent (with `<>`), `null` if missing                                         |
| `date`        | header `Date` as `DateTimeImmutable` with its time zone, `null` if missing or invalid               |
| `from`        | first address of `From` as `MailerAddress` (lower case, display name decoded), `null` if invalid    |
| `subject`     | decoded (RFC 2047, also over several lines and charsets), `''` if missing                          |
| `headers`     | all headers by lower case name (`list<string>` each), unfolded and decoded; `getHeader(name:)`      |
| `textBody`    | the inline `text/plain` parts; without them a text version of the `text/html` part                 |
| `rawMessage`  | the message as the server sent it                                                                 |

The text body is decoded (quoted-printable, base64, 7bit/8bit) and converted from its charset (ISO-8859-1 as
Windows-1252, unknown charsets as UTF-8 or Windows-1252). Attachments are ignored. The text version of HTML has no
tags, styles, scripts or comments, entities are decoded; paragraphs, headings, lists and tables are separated by an
empty line, rows, list items, `div` and `<br>` start a new line, table cells are separated by a space.

## Tests

`RecordingImapMailbox` holds the messages in memory by folder and UID and records what the code does (no network).
Pass it wherever the code expects an `ImapMailbox`:

```php
$parser = new MimeMessageParser();
$mailbox = new RecordingImapMailbox(folders: [
    'INBOX' => [1 => $parser->parse(rawMessage: file_get_contents(__DIR__ . '/fixtures/notification.eml'))],
    'INBOX.Processed' => [],
]);
$job->run(mailbox: $mailbox);
$this->assertSame(
    ['open mail.example.com:993', 'select INBOX', 'find 100', 'fetch 1', 'move 1 INBOX.Processed', 'close'],
    $mailbox->events,
);
$this->assertCount(1, $mailbox->folders['INBOX.Processed']);
```

A moved message gets the next free UID in its new folder. `addMessage(folder:, receivedMail:)` adds a message and
returns its UID; `openException:` makes `open()` throw (unreachable server).
