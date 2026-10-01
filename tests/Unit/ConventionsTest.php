<?php
/**
 * The conventions the plugin has to keep, checked on its own files.
 *
 * The core suite checks these for the plugins it ships (tests/Unit/Plugins/BuiltInPluginsTest.php),
 * and this plugin is not one of them, so the same ground is covered here. Two of the checks are
 * regression tests for defects the old version had in several places at once: a language key that was
 * used but never defined, and URLs that named the plugin directory or pointed at a path relative to
 * the page.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class ConventionsTest extends PluginTestCase
{
    /**
     * Every file of the plugin that is code, as path => code with the comments taken out.
     *
     * The comments go because these checks are about what the code does, and the code explains in its
     * comments what the old version did wrong - which would otherwise trip every one of them.
     *
     * @return array<string,string>
     */
    private function sources(): array
    {
        $sources = array();
        foreach ($this->rawSources() as $path => $content) {
            $sources[$path] = $this->stripComments($path, $content);
        }

        return $sources;
    }

    /**
     * @param string $path
     * @param string $content
     * @return string
     */
    private function stripComments(string $path, string $content): string
    {
        if (str_ends_with($path, '.php')) {
            $code = '';
            foreach (token_get_all($content) as $token) {
                if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }

            return $code;
        }

        if (str_ends_with($path, '.tpl')) {
            return (string)preg_replace('/\{\*.*?\*\}/s', '', $content);
        }

        if (str_ends_with($path, '.js') || str_ends_with($path, '.css')) {
            $content = (string)preg_replace('#/\*.*?\*/#s', '', $content);

            return (string)preg_replace('#^\s*//.*$#m', '', $content);
        }

        return $content;
    }

    /**
     * Every file of the plugin that is code, as path => contents.
     * @return array<string,string>
     */
    private function rawSources(): array
    {
        $root = dirname(__DIR__, 2);
        $patterns = array(
            '/plugin.php',
            '/plugin.json',
            '/modules/*.php',
            '/src/*.php',
            '/src/*/*.php',
            '/templates/*.tpl',
            '/assets/*.js',
            '/assets/*.css'
        );

        $sources = array();
        foreach ($patterns as $pattern) {
            foreach (glob($root . $pattern) as $file) {
                $sources[substr($file, strlen($root) + 1)] = (string)file_get_contents($file);
            }
        }

        $this->assertNotEmpty($sources);

        return $sources;
    }

    /**
     * @param string $path
     * @return array<int,string>
     */
    private function keysOf(string $path): array
    {
        $strings = simplexml_load_file($path);
        $this->assertNotFalse($strings, $path . ' is well-formed');

        $keys = array();
        foreach ($strings->string as $string) {
            $keys[] = (string)$string['name'];
        }

        return $keys;
    }

    /**
     * The guard that turns a direct request for the entry file into a message instead of a PHP error.
     * The core suite looks for this exact expression.
     */
    public function testTheEntryFileGuardsAgainstBeingCalledDirectly(): void
    {
        $this->assertStringContainsString(
            "realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__",
            (string)file_get_contents(dirname(__DIR__, 2) . '/plugin.php')
        );
    }

    /**
     * A key of the plugin has to be in the plugin's own language file, and any other key has to be one
     * the core defines.
     */
    public function testEveryLanguageKeyUsedIsDefined(): void
    {
        $ownKeys = $this->keysOf(dirname(__DIR__, 2) . '/languages/en.xml');
        $coreKeys = $this->keysOf(dirname(__DIR__, 4) . '/languages/en.xml');

        foreach ($this->sources() as $path => $content) {
            preg_match_all('/\b(PLG|SYS|ORG|INS)_[A-Z0-9_]+\b/', $content, $matches);

            foreach (array_unique($matches[0]) as $key) {
                if (str_starts_with($key, 'PLG_')) {
                    $this->assertContains($key, $ownKeys, $path . ' uses the undefined key ' . $key);
                } else {
                    $this->assertContains($key, $coreKeys, $path . ' uses the undefined core key ' . $key);
                }
            }
        }
    }

    /**
     * A page of a plugin is reachable as plugins/<id>/modules/<page> and, when the administrator
     * switches the core preference plugin_module_pages on, also as modules/<id>/<page>. So nothing may
     * name the directory of the plugin and nothing may point at a path relative to the page: the old
     * version did both, in the stylesheet link, the script link, the print-preview window and all
     * twenty-four screenshots of the manual.
     */
    public function testNoFileNamesThePluginDirectoryOrUsesARelativePath(): void
    {
        foreach ($this->sources() as $path => $content) {
            $this->assertStringNotContainsString(
                '/statistics/',
                $content,
                $path . ' names the plugin directory instead of asking the plugin for its URL'
            );
            $this->assertStringNotContainsString(
                '../resources',
                $content,
                $path . ' points at a path relative to the page'
            );
        }
    }

    /**
     * Where a URL below the plugin is built by hand - the manual and the screenshots, which stay in
     * resources/ - it is built from the id of the plugin object.
     */
    public function testEveryHandBuiltPluginUrlUsesThePluginId(): void
    {
        foreach ($this->sources() as $path => $content) {
            foreach (explode("\n", $content) as $number => $line) {
                if (!str_contains($line, 'FOLDER_PLUGINS')) {
                    continue;
                }

                $this->assertMatchesRegularExpression(
                    '/\$(plugin|this)->id/',
                    $line,
                    $path . ', line ' . ($number + 1) . ': a URL below plugins/ is built without the plugin id'
                );
            }
        }
    }

    /**
     * Every page requires a login before it does anything, which the old pages did inconsistently: one
     * required it only in the branch that refused access, and the script that saved and deleted did
     * not require it at all.
     *
     * @dataProvider pages
     */
    public function testEveryPageRequiresALogin(string $page): void
    {
        $content = (string)file_get_contents(dirname(__DIR__, 2) . '/modules/' . $page);

        $this->assertStringContainsString("/system/login_valid.php'", $content, $page);
    }

    /**
     * @dataProvider pages
     */
    public function testEveryPageAsksForThePermissionItNeeds(string $page): void
    {
        $content = (string)file_get_contents(dirname(__DIR__, 2) . '/modules/' . $page);
        $expected = $page === 'editor.php' ? 'assertMayConfigure()' : 'assertMayView()';

        $this->assertStringContainsString('StatisticsAccess::' . $expected, $content, $page);
    }

    /**
     * @return array<int,array<int,string>>
     */
    public static function pages(): array
    {
        return array(array('index.php'), array('show.php'), array('editor.php'), array('help.php'));
    }

    /**
     * Everything that changes data happens on a POST whose form object comes out of the session, which
     * is what validates the token. The old editor deleted a statistic on a GET and carried no token at
     * all.
     */
    public function testTheEditorActsOnlyOnAValidatedPost(): void
    {
        $content = (string)file_get_contents(dirname(__DIR__, 2) . '/modules/editor.php');

        $this->assertStringContainsString("\$_SERVER['REQUEST_METHOD'] ?? ''", $content);
        $this->assertStringContainsString("=== 'POST'", $content);
        $this->assertStringContainsString('getFormObject(', $content);
        $this->assertStringContainsString('->validate($_POST)', $content);

        // Every write happens after the method check, so none of them can be reached by a GET.
        $methodCheck = strpos($content, "=== 'POST'");
        $this->assertNotFalse($methodCheck);

        foreach (array('$repository->save(', '$repository->delete(', 'StatisticTemplates::create(') as $write) {
            $position = strpos($content, $write);
            $this->assertNotFalse($position, 'editor.php performs ' . $write);
            $this->assertGreaterThan($methodCheck, $position, $write . ' happens before the method check');
        }
    }

    /**
     * The counters the old editor submitted as hidden fields were the bounds of its loops, with no
     * ceiling: nr_of_tables=100000 made the server build a hundred thousand tables.
     */
    public function testTheClientSuppliedLoopCountersAreGone(): void
    {
        foreach ($this->sources() as $path => $content) {
            foreach (array('nr_of_tables', 'nr_of_columns', 'nr_of_rows') as $counter) {
                $this->assertStringNotContainsString($counter, $content, $path . ' still reads ' . $counter);
            }
        }
    }

    /**
     * The scratch row every administrator of every organization shared.
     */
    public function testNothingWritesToAReservedRow(): void
    {
        foreach ($this->sources() as $path => $content) {
            $this->assertStringNotContainsString('TEMPORARY STATISTIC', $content, $path);
            $this->assertStringNotContainsString('setTmpStatistic', $content, $path);
        }
    }

    /**
     * Every message the browser shows comes from the language files, so the script carries none.
     */
    public function testTheScriptCarriesNoText(): void
    {
        $script = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/statistics.editor.js');

        foreach (array('alert(', 'confirm("', "confirm('") as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script);
        }

        // The one dialog it does raise takes its words from a data attribute the server filled in.
        $this->assertStringContainsString("getAttribute('data-adm-confirm')", $script);
    }
}
