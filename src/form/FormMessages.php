<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

/**
 * The texts the form code creates itself. Plain text, encoded when it is rendered. The defaults are English,
 * `FormMessages::german()` has the German texts (Sie-form, Swiss spelling). Pass it to `Form(messages: ...)`.
 *
 * Placeholders: `[field]` in `invalidOption`, `[max]` in `tooManyFiles`, `[fileName]` in `duplicateFile`,
 * `[maxSize]` in `fileExceedsMaxSize`, `[min]` in `passwordTooShort`.
 */
final readonly class FormMessages
{
    public function __construct(
        public string $invalidInput = 'The invalid input was ignored.',
        public string $invalidValue = 'The given value is invalid.',
        public string $invalidZipCode = 'The entered zip code is invalid.',
        public string $selectOneOption = 'Please select one of the options.',
        public string $selectEmptyOption = '-- Please select --',
        public string $invalidOption = 'Selected invalid value in field [field]',
        public string $invalidCsrfToken = 'The form could not be submitted because of a technical problem'
        . ' (invalid CSRF token). Please try again.',
        public string $cancel = 'Cancel',
        public string $removeFile = 'remove',
        public string $fileEmpty = 'The file was empty:',
        public string $fileIncomplete = 'The file was uploaded incompletely:',
        public string $fileTooBig = 'The file was too big:',
        public string $fileTechnicalError = 'A technical error occurred while uploading the file:',
        public string $fileExceedsMaxSize = 'The file is larger than [maxSize]:',
        public string $fileTypeNotAllowed = 'The type of the file is not allowed:',
        public string $tooManyFiles = 'Only [max] file(s) allowed.',
        public string $duplicateFile = 'A file named "[fileName]" has already been uploaded.',
        public string $passwordTooShort = 'The password must have at least [min] characters.',
    ) {}

    public static function german(): FormMessages
    {
        return new FormMessages(
            invalidInput: 'Die ungültige Eingabe wurde ignoriert.',
            invalidValue: 'Der angegebene Wert ist ungültig.',
            invalidZipCode: 'Die eingegebene PLZ ist ungültig.',
            selectOneOption: 'Bitte wählen Sie eine der Optionen aus.',
            selectEmptyOption: '-- Bitte auswählen --',
            invalidOption: 'Ungültige Auswahl im Feld [field].',
            invalidCsrfToken: 'Das Formular konnte wegen eines technischen Problems (ungültiges CSRF-Token) nicht'
            . ' übermittelt werden. Bitte versuchen Sie es erneut.',
            cancel: 'Abbrechen',
            removeFile: 'löschen',
            fileEmpty: 'Die Datei war leer:',
            fileIncomplete: 'Die Datei wurde unvollständig hochgeladen:',
            fileTooBig: 'Die Datei war zu gross:',
            fileTechnicalError: 'Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten:',
            fileExceedsMaxSize: 'Die Datei ist grösser als [maxSize]:',
            fileTypeNotAllowed: 'Der Dateityp ist nicht erlaubt:',
            tooManyFiles: 'Nur [max] Datei(en) möglich.',
            duplicateFile: 'Es wurde bereits eine Datei mit dem Dateinamen "[fileName]" hochgeladen.',
            passwordTooShort: 'Das Passwort muss mindestens [min] Zeichen lang sein.',
        );
    }
}
