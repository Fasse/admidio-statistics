# Statistics

An Admidio plugin that builds statistics out of the profile data of the members of a role. A statistic
is a set of cross tables: each row and each column selects members by a profile field and a condition,
and each cell counts them or aggregates one of their fields. Configurations are saved, so a statistic
can be set up once and looked at whenever it is wanted.

Version 4 is the plugin ported to the native plugin system of Admidio 5.1. It installs, updates and
uninstalls through the plugin manager; the installer wizard of earlier versions is gone.

## Requirements

- Admidio 5.1 or newer
- PHP 8.2 or newer

## Installing

In Admidio, go to the plugin administration, install the plugin and enable it. That creates the four
database tables it needs, the preference that decides who may see it, and one menu entry under
Extensions.

Who may do what:

| | |
|---|---|
| Looking at the statistics | a valid login and the plugin's access preference, which is set to registered users by default. The menu entry has role rights of its own as well, editable in the menu administration. |
| Creating, changing and deleting them | an administrator |

The configuration editor is reached from the page functions menu of the statistics overview, and only
an administrator sees that entry.

## Upgrading from version 3

**Install the new version over the old one. Do not remove the old plugin first.**

1. Put the files of version 4 in place. Installing the archive through the plugin manager is the
   cleanest way: it replaces the directory, so nothing of the old layout is left behind.
2. Install, or simply enable, the plugin in the plugin manager. Version 3 never registered itself as a
   component, so the manager lists it as available rather than installed.

No saved statistic is lost. The four tables keep their names, and the installation script finds them
already there and leaves them alone. What it does remove is the wreckage of version 3: the row the old
editor reserved as a scratch pad, any statistic that ended up belonging to no organization, and the two
menu entries the old installer inserted by hand.

### Figures that change

Version 4 fixes four faults in the calculation, so some saved statistics will show different - correct
- numbers afterwards. This is worth looking at once after the upgrade:

- **Two conditions are intersected.** A cell counts the members that meet the condition of its row
  *and* the condition of its column. Version 3 counted how often a member came back from its queries,
  and two of those queries could return the same member several times, so a member meeting one of two
  conditions could be counted as meeting both.
- **The four aggregating functions agree with the counting one.** Minimum, maximum, average and sum
  asked for the members that met *either* condition, while the count in the same table asked for those
  that met both.
- **A total row belongs only to a column that asks for one.** Version 3 put one under every table.
- **A minimum is the smallest value.** Version 3 started looking from 10000, so a column whose figures
  were all larger reported 10000.

## Removing the plugin

**Removing the plugin drops its four tables and every statistic saved in them.** There is no way back
short of a database backup. Disabling the plugin is always safe: it hides the plugin and changes no
data.

## The manual

The plugin carries its own manual, in German, with 49 chapters and screenshots. Every field of the
editor links to the chapter that explains it, and the whole manual is reachable from the editor as
well. It lives in `resources/`, together with the PDF of the same text.

## Development

The tests run with the test environment of Admidio, from the Admidio directory:

```bash
php vendor/bin/phpunit plugins/statistics/tests
```

The unit tests need no database. The integration tests use the configured test database, as
`tests/README.md` of Admidio describes.

## License, author and source

GNU General Public License v2.0 only, see `LICENSE`.

Written by Fasse (formerly kcn/Alexn). The plugin page, with further information, is at
<https://www.admidio.org/dokuwiki/doku.php?id=en:plugins:statistics>; the source is at
<https://github.com/Fasse/admidio-statistics>.
