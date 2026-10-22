# Plan: named arguments for `session_set_save_handler()` with PHP 9

`AbstractSessionHandler::ensureStarted()` calls `session_set_save_handler($this, true)` without named arguments, as an
exception to the coding standard: the function has two prototypes, and PHP only knows the parameter names of the
deprecated callable prototype (`open`, `close`, `read`, …). A named call of the object prototype fails with
`Error: Unknown named parameter $sessionhandler` (checked with PHP 8.5.8).

- Docs: https://www.php.net/manual/en/function.session-set-save-handler.php, updated after
  https://github.com/php/doc-en/issues/4341
- Cause: https://github.com/php/php-src/issues/17263
- The callable prototype is deprecated since PHP 8.4 and is to be removed in PHP 9
  (https://wiki.php.net/rfc/deprecations_php_8_4). yuf uses the object prototype only, so nothing breaks.

## Trigger

Check again as soon as the first PHP 9 release candidate is out (or earlier, when php-src removes the callable
prototype).

## Steps

1. **Check (no release):** on PHP 9, list the parameter names PHP knows:

   ```php
   array_map(
       callback: static fn(ReflectionParameter $parameter): string => $parameter->getName(),
       array: new ReflectionFunction(function: 'session_set_save_handler')->getParameters(),
   );
   ```

   - Still `open`, `close`, …: the callable prototype is still there, nothing to do; check again with the next major.
   - Object prototype only (e.g. `sessionhandler`, `register_shutdown`; the names may differ from the docs): step 2.
2. **Switch to named arguments (release with the PHP 9 support):** in `AbstractSessionHandler::ensureStarted()` call
   `session_set_save_handler(sessionhandler: $this, register_shutdown: true);` with the names from step 1, and remove
   the comment about the alternative prototypes above it. No API change, no `UPGRADE.md` entry needed.

## Handover notes
