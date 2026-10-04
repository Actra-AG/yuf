<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

/**
 * The texts the form code creates itself. Plain text, encoded when it is rendered. The defaults are English,
 * `FormMessages::german()` has the texts of yuf v3. Pass it to `Form(messages: ...)`.
 *
 * Placeholders: `[field]` in `invalidOption`, `[max]` in `tooManyFiles`, `[fileName]` in `duplicateFile`.
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
        public string $tooManyFiles = 'Only [max] file(s) allowed.',
        public string $duplicateFile = 'A file named "[fileName]" has already been uploaded.'
    ) {
    }

    public static function german(): FormMessages
    {
        return new FormMessages(
            invalidInput: 'Die ungültige Eingabe wurde ignoriert.',
            invalidValue: 'Der angegebene Wert ist ungültig.',
            invalidZipCode: 'Die eingegebene PLZ ist ungültig.',
            selectOneOption: 'Bitte wählen Sie eine der Optionen aus.',
            selectEmptyOption: '-- Please select --',
            invalidOption: 'Selected invalid value in field [field]',
            invalidCsrfToken: 'Das Formular konnte wegen eines technischen Problems (ungültiges CSRF) nicht'
            . ' übermittelt werden. Bitte versuchen Sie es erneut.',
            cancel: 'Abbrechen',
            removeFile: 'löschen',
            fileEmpty: 'Die Datei war leer:',
            fileIncomplete: 'Die Datei wurde unvollständig hochgeladen:',
            fileTooBig: 'Die Datei war zu gross:',
            fileTechnicalError: 'Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten:',
            tooManyFiles: 'Nur [max] Datei(en) möglich.',
            duplicateFile: 'Es wurde bereits eine Datei mit dem Dateinamen "[fileName]" hochgeladen.'
        );
    }
}