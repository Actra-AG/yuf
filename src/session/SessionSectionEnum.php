<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

/**
 * The sections of the session data of yuf: everything yuf stores lives in `$_SESSION['yuf'][<section>]`. Data of the
 * project is stored next to it (`Session::set()`), so the two cannot collide. `HANDLER` is the only section that
 * survives `Session::clearUserData()`.
 */
enum SessionSectionEnum: string
{
    /** The key below which yuf keeps all its data. */
    public const string ROOT_KEY = 'yuf';

    /** Data of the session handler: creation time, trusted client, last activity, preferred language. */
    case HANDLER = 'handler';
    /** Login state (`AuthSession`). */
    case AUTH = 'auth';
    /** The CSRF token (`SessionCsrfTokenSource`). */
    case CSRF = 'csrf';
    /** Sorting and page of the tables (`DbResultTable`). */
    case TABLES = 'tables';
    /** The values of table filters and their fields (`TableFilter`). */
    case TABLE_FILTERS = 'tableFilters';
    /** The state of the search forms (`SearchHelper`). */
    case SEARCH = 'search';
    /** The pointers to uploaded files (`SessionFileUploadStorage`). */
    case UPLOADS = 'uploads';
}
