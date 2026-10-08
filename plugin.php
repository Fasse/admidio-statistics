<?php
/**
 ***********************************************************************************************
 * Statistics - entry file.
 *
 * The entry file is included once when the plugin is loaded. This plugin contributes only its own
 * pages and a settings panel that Admidio generates from the manifest, so there is nothing to
 * register here: the pages are found in modules/, the menu entry is created when the plugin is
 * installed, and the panel is built from the "settings" and "preferences" keys of plugin.json.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/*
 * This file is included by the plugin loader and is never an entry point of its own. Without the
 * Admidio bootstrap it could do nothing anyway; the guard only turns a PHP error into a message.
 */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit('This page may not be called directly!');
}
