<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

/**
 * The texts the form code creates itself. Plain text, encoded when it is rendered. The defaults are English;
 * `FormMessages::german()`, `french()` and `italian()` have the German, French and Italian texts (formal address, Swiss
 * usage). Pass it to `Form(messages: ...)`.
 *
 * Placeholders: `[field]` in `invalidOption`, `[max]` in `tooManyFiles`, `[fileName]` in `duplicateFile`,
 * `[maxSize]` in `fileExceedsMaxSize`, `[min]` in `passwordTooShort`, `[max]` in `tooLong`. `spamHoneypotFilled` names
 * the label `spamHoneypotLabel`: change both together.
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
        public string $spamHoneypotLabel = 'For internal use only',
        public string $spamHoneypotFilled = 'Please leave the field labelled "For internal use only" empty.',
        public string $spamRetry = 'Please try again.',
        public string $spamRejectedEmailAddress = 'Please enter a valid e-mail address.',
        public string $tooLong = 'The text must not be longer than [max] characters.',
        public string $rateLimited = 'Too many submissions. Please try again in a few minutes.',
        public string $captchaFailed = 'The security check failed. Please confirm again that you are human.',
        public string $captchaUnavailable = 'The security check is not available at the moment. Please try again'
        . ' later.',
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
            spamHoneypotLabel: 'Nur für interne Zwecke',
            spamHoneypotFilled: 'Bitte lassen Sie das Feld mit der Bezeichnung "Nur für interne Zwecke" leer.',
            spamRetry: 'Bitte versuchen Sie es erneut.',
            spamRejectedEmailAddress: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            tooLong: 'Der Text darf höchstens [max] Zeichen lang sein.',
            rateLimited: 'Zu viele Übermittlungen. Bitte versuchen Sie es in einigen Minuten erneut.',
            captchaFailed: 'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte bestätigen Sie erneut, dass Sie ein Mensch'
            . ' sind.',
            captchaUnavailable: 'Die Sicherheitsprüfung ist im Moment nicht verfügbar. Bitte versuchen Sie es später'
            . ' erneut.',
        );
    }

    public static function french(): FormMessages
    {
        return new FormMessages(
            invalidInput: 'La saisie non valable a été ignorée.',
            invalidValue: 'La valeur indiquée n’est pas valable.',
            invalidZipCode: 'Le NPA saisi n’est pas valable.',
            selectOneOption: 'Veuillez sélectionner l’une des options.',
            selectEmptyOption: '-- Veuillez sélectionner --',
            invalidOption: 'Sélection non valable dans le champ [field].',
            invalidCsrfToken: 'Le formulaire n’a pas pu être envoyé en raison d’un problème technique (jeton CSRF '
                . 'non valable). Veuillez réessayer.',
            cancel: 'Annuler',
            removeFile: 'supprimer',
            fileEmpty: 'Le fichier était vide:',
            fileIncomplete: 'Le fichier n’a été que partiellement téléchargé:',
            fileTooBig: 'Le fichier était trop volumineux:',
            fileTechnicalError: 'Une erreur technique s’est produite lors du téléchargement du fichier:',
            fileExceedsMaxSize: 'Le fichier dépasse [maxSize]:',
            fileTypeNotAllowed: 'Ce type de fichier n’est pas autorisé:',
            tooManyFiles: '[max] fichier(s) au maximum.',
            duplicateFile: 'Un fichier nommé «[fileName]» a déjà été téléchargé.',
            passwordTooShort: 'Le mot de passe doit comporter au moins [min] caractères.',
            spamHoneypotLabel: 'Réservé à un usage interne',
            spamHoneypotFilled: 'Veuillez laisser vide le champ «Réservé à un usage interne».',
            spamRetry: 'Veuillez réessayer.',
            spamRejectedEmailAddress: 'Veuillez saisir une adresse e-mail valable.',
            tooLong: 'Le texte ne doit pas dépasser [max] caractères.',
            rateLimited: 'Trop d’envois. Veuillez réessayer dans quelques minutes.',
            captchaFailed: 'Le contrôle de sécurité a échoué. Veuillez confirmer à nouveau que vous êtes un humain.',
            captchaUnavailable: 'Le contrôle de sécurité n’est pas disponible pour le moment. Veuillez réessayer '
                . 'plus tard.',
        );
    }

    public static function italian(): FormMessages
    {
        return new FormMessages(
            invalidInput: 'L’inserimento non valido è stato ignorato.',
            invalidValue: 'Il valore indicato non è valido.',
            invalidZipCode: 'Il NPA inserito non è valido.',
            selectOneOption: 'Selezioni una delle opzioni.',
            selectEmptyOption: '-- Selezionare --',
            invalidOption: 'Selezione non valida nel campo [field].',
            invalidCsrfToken: 'Non è stato possibile inviare il modulo a causa di un problema tecnico (token CSRF '
                . 'non valido). Riprovi.',
            cancel: 'Annullare',
            removeFile: 'eliminare',
            fileEmpty: 'Il file era vuoto:',
            fileIncomplete: 'Il file è stato caricato in modo incompleto:',
            fileTooBig: 'Il file era troppo grande:',
            fileTechnicalError: 'Si è verificato un errore tecnico durante il caricamento del file:',
            fileExceedsMaxSize: 'Il file è più grande di [maxSize]:',
            fileTypeNotAllowed: 'Il tipo di file non è consentito:',
            tooManyFiles: 'Al massimo [max] file.',
            duplicateFile: 'È già stato caricato un file con il nome «[fileName]».',
            passwordTooShort: 'La password deve contenere almeno [min] caratteri.',
            spamHoneypotLabel: 'Solo per uso interno',
            spamHoneypotFilled: 'Lasci vuoto il campo «Solo per uso interno».',
            spamRetry: 'Riprovi.',
            spamRejectedEmailAddress: 'Inserisca un indirizzo e-mail valido.',
            tooLong: 'Il testo non può superare [max] caratteri.',
            rateLimited: 'Troppi invii. Riprovi tra qualche minuto.',
            captchaFailed: 'Il controllo di sicurezza non è riuscito. Confermi di nuovo di essere una persona.',
            captchaUnavailable: 'Il controllo di sicurezza non è disponibile al momento. Riprovi più tardi.',
        );
    }
}
