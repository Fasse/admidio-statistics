# Statistics

An Admidio plugin that builds statistics out of the profile data of the members of a role. A statistic
is a set of cross tables: each row and each column selects members by a profile field and a condition,
and each cell counts them or aggregates one of their fields. Configurations are saved, so a statistic
can be set up once and looked at whenever it is wanted.

Version 4 is the plugin ported to the native plugin system of Admidio 6.0. It installs, updates and
uninstalls through the plugin manager; the installer wizard of earlier versions is gone.

## Requirements

- Admidio 6.0 or newer
- PHP 8.2 or newer

## Installing

In Admidio, go to the plugin administration, install the plugin and enable it. That creates the six
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

The plugin manager lists the folder of version 3 as a plugin made for an earlier Admidio version and
offers "Update to the current version". That replaces the files and installs the new version at once.
Instead, you can put the files of the new version in place by hand and then install the plugin in the
plugin manager. Version 3 never registered itself as a component, so the manager lists it as
available rather than installed.

No saved statistic is lost. The four tables keep their names; the installation creates the tables
that are missing and adds the new columns to the old ones. It removes the wreckage of version 3: the
row the old editor reserved as a scratch pad and any statistic that ended up belonging to no
organization. The menu entry of the overview that the old installer inserted by hand becomes the
entry of the plugin, with the name, position and role rights it was given. The entry of the old
editor is removed, because the editor has no page of its own any more.

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

## New in version 4.1

- **A row and a column can name a role and a reference date.** A role stands in for the role of the
  table, a reference date for today: the cell counts who was a member of that role on that day. One
  row per role and one column per date give the development of the member numbers over the years. A
  row or column without a label is named after its role or its date. When its role is deleted, it
  counts the role of its table again.
- **A reference date can repeat** every year, every month or every week. Such a row or column stands
  for a series of days up to today, and gets its next day by itself: a column for the first of
  January of every year needs no editing when a new year begins. "At most" limits how many of the
  latest days are shown (10 unless set, 366 at most).
- **Excluded roles.** A statistic can name roles whose members it leaves out of every figure. A
  member is left out on the days they were in such a role.
- **Any statistic can be shown for another day**, with the date field above the result - unless its
  rows or columns name days of their own.
- A new example, "Membership development", shows the first two together.
- **A statistic can be shown on the overview page.** The checkbox "Show on the overview page" in the
  editor puts it into the statistics widget there, for logged-in users. The figures of the widget are
  cached: they are computed again when the statistic was edited, when a membership or a profile has
  changed, and on every new day.

Only the memberships have a history. A condition on a profile field always tests the value the
member has today.

### Figures that change

Version 4.0 counted the memberships that have no end date. Version 4.1 counts the memberships that
include the day in question, which is how the profile of a member shows them. Today's figures
therefore now include a member whose membership has an end date in the future, and no longer include
one whose membership has not begun.

### Updating

From 4.0, the plugin manager offers the update, which adds nine columns and two tables. Version 3 is
updated to 4.1 directly, as described above.

## Removing the plugin

**Removing the plugin drops its six tables and every statistic saved in them.** There is no way back
short of a database backup. Disabling the plugin is always safe: it hides the plugin and changes no
data.

## The manual

The plugin carries its own manual, in German, with 57 chapters and screenshots. Every field of the
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
