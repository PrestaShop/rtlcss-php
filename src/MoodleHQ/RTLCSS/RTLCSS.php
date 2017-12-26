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
     * List of transformations to perform for each property, in order
     * @var TransformationInterface[]
     */
    protected $transformationQueue = [];

    /**
     * Options specifying opinionated flipping choices
     * @var FlipOptions
     */
    protected $options;

    /**
     * RTLCSS constructor.
     *
     * @param Document $tree
     * @param FlipOptions $options [default=null]
     */
    public function __construct(Document $tree, FlipOptions $options = null) {
        $this->tree = $tree;
        $this->options = ($options !== null) ? $options : new FlipOptions();
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
            new FlipBackground($this->options),
            new FlipCursor(),
        ];
    }

    /**
     * @return Document
     */
    public function flip() {
        $this->processBlock($this->tree);
        return $this->tree;
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
}
