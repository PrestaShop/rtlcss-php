<?php
/**
 * RTLCSS.
 *
 * @package   MoodleHQ\RTLCSS
 * @copyright 2016 Frédéric Massart - FMCorz.net
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace MoodleHQ\RTLCSS;

use MoodleHQ\RTLCSS\Transformation\FlipBackground;
use MoodleHQ\RTLCSS\Transformation\FlipBorderRadius;
use MoodleHQ\RTLCSS\Transformation\FlipCursor;
use MoodleHQ\RTLCSS\Transformation\FlipDirection;
use MoodleHQ\RTLCSS\Transformation\FlipLeftProperty;
use MoodleHQ\RTLCSS\Transformation\FlipLeftValue;
use MoodleHQ\RTLCSS\Transformation\FlipMarginPaddingBorder;
use MoodleHQ\RTLCSS\Transformation\FlipRightProperty;
use MoodleHQ\RTLCSS\Transformation\FlipShadow;
use MoodleHQ\RTLCSS\Transformation\FlipTransform;
use MoodleHQ\RTLCSS\Transformation\FlipTransformOrigin;
use MoodleHQ\RTLCSS\Transformation\FlipTransition;
use MoodleHQ\RTLCSS\Transformation\TransformationInterface;
use Sabberworm\CSS\Comment\Comment;
use Sabberworm\CSS\CSSList\CSSBlockList;
use Sabberworm\CSS\CSSList\CSSList;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\RuleSet\RuleSet;
use Sabberworm\CSS\Value\CSSFunction;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Size;
use Sabberworm\CSS\Value\ValueList;

/**
 * RTLCSS Class.
 *
 * @package   MoodleHQ\RTLCSS
 * @copyright 2016 Frédéric Massart - FMCorz.net
 * @license   https://opensource.org/licenses/MIT MIT
 */
class RTLCSS {

    /**
     * @var Document
     */
    protected $tree;

    /**
     * @var array
     */
    protected $shouldAddCss = [];

    /**
     * @var bool
     */
    protected $shouldIgnore = false;

    /**
     * @var bool
     */
    protected $shouldRemove = false;

    /**
     * @var TransformationInterface[]
     */
    protected $transformationQueue = [];

    /**
     * RTLCSS constructor.
     *
     * @param Document $tree
     */
    public function __construct(Document $tree) {
        $this->tree = $tree;
        $this->transformationQueue = [
            new FlipDirection(),
            new FlipLeftProperty(),
            new FlipRightProperty(),
            new FlipTransition(),
            new FlipLeftValue(),
            new FlipMarginPaddingBorder(),
            new FlipBorderRadius(),
            new FlipShadow(),
            new FlipTransformOrigin(),
            new FlipTransform(),
            new FlipBackground(),
            new FlipCursor(),
        ];
    }

    /**
     * @param string $what
     * @param string $to
     * @param bool $ignoreCase
     *
     * @return bool
     */
    protected function compare($what, $to, $ignoreCase) {
        if ($ignoreCase) {
            return strtolower($what) === strtolower($to);
        }
        return $what === $to;
    }

    /**
     * @param Size|CSSFunction $value
     */
    protected function complement($value) {
        if ($value instanceof Size) {
            $value->setSize(100 - $value->getSize());

        } else if ($value instanceof CSSFunction) {
            $arguments = implode($value->getListSeparator(), $value->getArguments());
            $arguments = "100% - ($arguments)";
            $value->setListComponents([$arguments]);
        }
    }

    /**
     * @return Document
     */
    public function flip() {
        $this->processBlock($this->tree);
        return $this->tree;
    }

    /**
     * @param ValueList|Size $value
     */
    protected function negate($value) {
        if ($value instanceof ValueList) {
            foreach ($value->getListComponents() as $part) {
                $this->negate($part);
            }
        } else if ($value instanceof Size) {
            if ($value->getSize() != 0) {
                $value->setSize(-$value->getSize());
            }
        }
    }

    /**
     * @param Comment[] $comments
     */
    protected function parseComments(array $comments) {
        $startRule = '^(\s|\*)*!?rtl:';
        foreach ($comments as $comment) {
            $content = $comment->getComment();
            if (preg_match('/' . $startRule . 'ignore/', $content)) {
                $this->shouldIgnore = 1;
            } else if (preg_match('/' . $startRule . 'begin:ignore/', $content)) {
                $this->shouldIgnore = true;
            } else if (preg_match('/' . $startRule . 'end:ignore/', $content)) {
                $this->shouldIgnore = false;
            } else if (preg_match('/' . $startRule . 'remove/', $content)) {
                $this->shouldRemove = 1;
            } else if (preg_match('/' . $startRule . 'begin:remove/', $content)) {
                $this->shouldRemove = true;
            } else if (preg_match('/' . $startRule . 'end:remove/', $content)) {
                $this->shouldRemove = false;
            } else if (preg_match('/' . $startRule . 'raw:/', $content)) {
                $this->shouldAddCss[] = preg_replace('/' . $startRule . 'raw:/', '', $content);
            }
        }
    }

    /**
     * @param Rule $rule
     */
    protected function processBackground(Rule $rule) {
        $value = $rule->getValue();

        // TODO Fix upstream library as it does not parse this well, commas don't take precedence.
        // There can be multiple sets of properties per rule.
        $hasItems = false;
        $items = [$value];
        if ($value instanceof RuleValueList && $value->getListSeparator() == ',') {
            $hasItems = true;
            $items = $value->getListComponents();
        }

        // Foreach set.
        foreach ($items as $itemKey => $item) {

            // There can be multiple values in the same set.
            $hasValues = false;
            $parts = [$item];
            if ($item instanceof RuleValueList) {
                $hasValues = true;
                $parts = $value->getListComponents();
            }

            $requiresPositionalArgument = false;
            $hasPositionalArgument = false;
            foreach ($parts as $key => $part) {
                $part = $parts[$key];

                if (!is_object($part)) {
                    $flipped = $this->swapLeftRight($part);

                    // Positional arguments can have a size following.
                    $hasPositionalArgument = $parts[$key] != $flipped;
                    $requiresPositionalArgument = true;

                    $parts[$key] = $flipped;
                    continue;

                } else if ($part instanceof CSSFunction && strpos($part->getName(), 'gradient') !== false) {
                    // TODO Fix this.

                } else if ($part instanceof Size && ($part->getUnit() === '%' || !$part->getUnit())) {

                    // Is this a value we're interested in?
                    if (!$requiresPositionalArgument || $hasPositionalArgument) {
                        $this->complement($part);
                        $part->setUnit('%');
                        // We only need to change one value.
                        break;
                    }

                }

                $hasPositionalArgument = false;
            }

            if ($hasValues) {
                $item->setListComponents($parts);
            } else {
                $items[$itemKey] = $parts[$key];
            }
        }

        if ($hasItems) {
            $value->setListComponents($items);
        } else {
            $rule->setValue($items[0]);
        }
    }

    /**
     * @param CSSBlockList $block
     */
    protected function processBlock($block) {
        $contents = [];

        /** @var RuleSet $node */
        foreach ($block->getContents() as $node) {
            $this->parseComments($node->getComments());

            if ($toAdd = $this->shouldAddCss()) {
                foreach ($toAdd as $add) {
                    $parser = new Parser($add);
                    $contents[] = $parser->parse();
                }
            }

            if ($this->shouldRemoveNext()) {
                continue;

            } else if (!$this->shouldIgnoreNext()) {
                if ($node instanceof CSSList) {
                    $this->processBlock($node);
                }
                if ($node instanceof RuleSet) {
                    $this->processDeclaration($node);
                }
            }

            $contents[] = $node;
        }

        $block->setContents($contents);
    }

    /**
     * @param RuleSet $node
     */
    protected function processDeclaration($node) {
        $rules = [];

        foreach ($node->getRules() as $key => $rule) {
            $this->parseComments($rule->getComments());

            if ($toAdd = $this->shouldAddCss()) {
                foreach ($toAdd as $add) {
                    $parser = new Parser('.wrapper{' . $add . '}');
                    $tree = $parser->parse();
                    $contents = $tree->getContents();
                    foreach ($contents[0]->getRules() as $newRule) {
                        $rules[] = $newRule;
                    }
                }
            }

            if ($this->shouldRemoveNext()) {
                continue;

            } else if (!$this->shouldIgnoreNext()) {
                $this->processRule($rule);
            }

            $rules[] = $rule;
        }

        $node->setRules($rules);
    }

    /**
     * @param Rule $rule
     */
    protected function processRule(Rule $rule) {
        $property = $rule->getRule();
        foreach ($this->transformationQueue as $transformation) {
            if ($transformation->appliesFor($property)) {
                $transformation->transform($rule);
                break;
            }
        }
    }

    /**
     * @param Rule $rule
     */
    protected function processTransformOrigin(Rule $rule) {
        $value = $rule->getValue();
        $foundLeftOrRight = false;

        // Search for left or right.
        $parts = [$value];
        if ($value instanceof RuleValueList) {
            $parts = $value->getListComponents();
            $isInList = true;
        }
        foreach ($parts as $key => $part) {
            if (!is_object($part) && preg_match('/left|right/i', $part)) {
                $foundLeftOrRight = true;
                $parts[$key] = $this->swapLeftRight($part);
            }
        }

        if ($foundLeftOrRight) {
            // We need to reconstruct the value because left/right are not represented by an object.
            $list = new RuleValueList(' ');
            $list->setListComponents($parts);
            $rule->setValue($list);

        } else {

            $value = $parts[0];
            // The first value may be referencing top or bottom (y instead of x).
            if (!is_object($value) && preg_match('/top|bottom/i', $value)) {
                $value = $parts[1];
            }

            // Flip the value.
            if ($value instanceof Size) {

                if ($value->getSize() == 0) {
                    $value->setSize(100);
                    $value->setUnit('%');

                } else if ($value->getUnit() === '%') {
                    $this->complement($value);
                }

            } else if ($value instanceof CSSFunction && strpos($value->getName(), 'calc') !== false) {
                // TODO Fix upstream calc parsing.
                $this->complement($value);
            }
        }
    }

    /**
     * @return array
     */
    protected function shouldAddCss() {
        if (!empty($this->shouldAddCss)) {
            $css = $this->shouldAddCss;
            $this->shouldAddCss = [];
            return $css;
        }
        return [];
    }

    /**
     * @return bool
     */
    protected function shouldIgnoreNext() {
        if ($this->shouldIgnore) {
            if (is_int($this->shouldIgnore)) {
                $this->shouldIgnore--;
            }
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    protected function shouldRemoveNext() {
        if ($this->shouldRemove) {
            if (is_int($this->shouldRemove)) {
                $this->shouldRemove--;
            }
            return true;
        }
        return false;
    }

    /**
     * @param $value
     * @param $a
     * @param $b
     * @param array $options
     *
     * @return null|string|string[]
     */
    protected function swap($value, $a, $b, $options = ['scope' => '*', 'ignoreCase' => true]) {
        $expr = preg_quote($a) . '|' . preg_quote($b);
        if (!empty($options['greedy'])) {
            $expr = '\\b(' . $expr . ')\\b';
        }
        $flags = !empty($options['ignoreCase']) ? 'im' : 'm';
        $expr = "/$expr/$flags";
        return preg_replace_callback($expr, function($matches) use ($a, $b, $options) {
            return $this->compare($matches[0], $a, !empty($options['ignoreCase'])) ? $b : $a;
        }, $value);
    }

    /**
     * @param $value
     *
     * @return null|string|string[]
     */
    protected function swapLeftRight($value) {
        return $this->swap($value, 'left', 'right');
    }

    /**
     * @param $value
     *
     * @return null|string|string[]
     */
    protected function swapLtrRtl($value) {
        return $this->swap($value, 'ltr', 'rtl');
    }

}
