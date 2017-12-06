<?php
namespace CSSJanus;

use MoodleHQ\RTLCSS\RTLCSS;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;

class CSSJanusTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideData
     */
    public function testTransform($input, $args, $expected, $name) {
        $output = new OutputFormat();
        $output
            ->set('SpaceBeforeRules', ' ')
            ->set('SpaceAfterRules', ' ')
            ->set('SpaceBetweenRules', ' ')
            ->set('SpaceBetweenBlocks', ' ')
            ->set('SpaceAfterListArgumentSeparator', array('default' => '', ',' => ' '));

        $parser = new Parser($input);
        $tree = $parser->parse();
        $rtlcss = new RTLCSS($tree);
        $flipped = $rtlcss->flip();

        $this->assertEquals(
            $expected,
            $flipped->render($output),
            $name
        );
    }

    public static function provideData()
    {
        $data = self::getSpec();
        $cases = array();
        foreach ($data as $name => $test) {
            $args = [];
            if (isset($test['args']) || isset($test['options'])) {
                // v1.2.0 test format
                $args = isset($test['args']) ? $test['args'] :
                    (isset($test['options']) ? array($test['options']) : array());
            }

            foreach ($test['cases'] as $i => $case) {
                $input = $case[0];
                $noop = !isset($case[1]);
                $output = $noop ? $input : $case[1];
                $caseNumber = $i + 1;
                $caseName = "$name $caseNumber";

                $cases[$caseName] = array(
                    $input,
                    $args,
                    $output,
                    $caseName,
                );

                if (!$noop) {
                    // Round trip
                    $cases["$caseName (undo)"] = array(
                        $output,
                        $args,
                        $input,
                        "$caseName (undo)",
                    );
                }
            }
        }
        return $cases;
    }

    protected static function getSpec() {
        static $json;
        if ($json == null) {
            $version = '1.2.1';
            $dir = dirname(__FILE__);
            $file = "$dir/cssjanus-$version-adapted.json";
            $json = file_get_contents($file);
        }
        return json_decode($json, true);
    }
}
