# Upgrade Guide

This document tracks relevant changes for both frontend and backend developers.

## HTML & CSS (Frontend)

### May 6, 2026
* **ActionsColumn Rendering:** The `<ul>` and `<li>` tags were removed. Multiple action links are now simply separated by a newline (`PHP_EOL`).

## Backend & API

### May 6, 2026
* **ActionsColumn Constants:** Added `ActionsColumn::EDIT` and `ActionsColumn::DELETE` constants for action link identifiers to improve type safety and extensibility.

### April 25, 2026
* **DbSettingsModel Defaults:** The constructor now provides default values for `charset` (utf8mb4), `timeNamesLanguage` (de_CH), and `sqlSafeUpdates` (true).
* **Routing & Layout:** Introduced `NavigationItem` and `NavigationItemCollection` for structured layout management. Centralized routing logic now supports custom view directories and class prefixes.

### March 15, 2026
* **Core Autoloader:** Replaced internal autoloader with `actra/autoloader`. The `Core` constructor now requires the path to the autoloader if not using the default location.

### March 11, 2026
* **Database (FrameworkDB):** `PDO::ATTR_STRINGIFY_FETCHES` is now set to `false`. Database results will now return numeric types (int/float) as their respective PHP types instead of strings.
