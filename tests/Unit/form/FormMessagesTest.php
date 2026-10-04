<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\FormMessages;
use PHPUnit\Framework\TestCase;

final class FormMessagesTest extends TestCase
{
    public function testDefaultsAreEnglish(): void
    {
        $messages = new FormMessages();

        $this->assertSame('The invalid input was ignored.', $messages->invalidInput);
        $this->assertSame('The given value is invalid.', $messages->invalidValue);
        $this->assertSame('The entered zip code is invalid.', $messages->invalidZipCode);
        $this->assertSame('Please select one of the options.', $messages->selectOneOption);
        $this->assertSame('-- Please select --', $messages->selectEmptyOption);
        $this->assertSame('Selected invalid value in field [field]', $messages->invalidOption);
        $this->assertSame(
            'The form could not be submitted because of a technical problem (invalid CSRF token). Please try again.',
            $messages->invalidCsrfToken
        );
        $this->assertSame('Cancel', $messages->cancel);
        $this->assertSame('remove', $messages->removeFile);
        $this->assertSame('The file was empty:', $messages->fileEmpty);
        $this->assertSame('The file was uploaded incompletely:', $messages->fileIncomplete);
        $this->assertSame('The file was too big:', $messages->fileTooBig);
        $this->assertSame('A technical error occurred while uploading the file:', $messages->fileTechnicalError);
        $this->assertSame('Only [max] file(s) allowed.', $messages->tooManyFiles);
        $this->assertSame('A file named "[fileName]" has already been uploaded.', $messages->duplicateFile);
    }

    public function testGermanHasTheTextsOfVersion3(): void
    {
        $messages = FormMessages::german();

        $this->assertSame('Die ungültige Eingabe wurde ignoriert.', $messages->invalidInput);
        $this->assertSame('Der angegebene Wert ist ungültig.', $messages->invalidValue);
        $this->assertSame('Die eingegebene PLZ ist ungültig.', $messages->invalidZipCode);
        $this->assertSame('Bitte wählen Sie eine der Optionen aus.', $messages->selectOneOption);
        $this->assertSame('-- Please select --', $messages->selectEmptyOption);
        $this->assertSame('Selected invalid value in field [field]', $messages->invalidOption);
        $this->assertSame(
            'Das Formular konnte wegen eines technischen Problems (ungültiges CSRF) nicht übermittelt werden.'
            . ' Bitte versuchen Sie es erneut.',
            $messages->invalidCsrfToken
        );
        $this->assertSame('Abbrechen', $messages->cancel);
        $this->assertSame('löschen', $messages->removeFile);
        $this->assertSame('Die Datei war leer:', $messages->fileEmpty);
        $this->assertSame('Die Datei wurde unvollständig hochgeladen:', $messages->fileIncomplete);
        $this->assertSame('Die Datei war zu gross:', $messages->fileTooBig);
        $this->assertSame(
            'Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten:',
            $messages->fileTechnicalError
        );
        $this->assertSame('Nur [max] Datei(en) möglich.', $messages->tooManyFiles);
        $this->assertSame(
            'Es wurde bereits eine Datei mit dem Dateinamen "[fileName]" hochgeladen.',
            $messages->duplicateFile
        );
    }

    public function testIndividualTextsOverrideTheDefaultsAndTheRestStaysEnglish(): void
    {
        $messages = new FormMessages(invalidInput: 'Ungültig.', cancel: 'Zurück');

        $this->assertSame('Ungültig.', $messages->invalidInput);
        $this->assertSame('Zurück', $messages->cancel);
        $this->assertSame('The given value is invalid.', $messages->invalidValue);
    }
}