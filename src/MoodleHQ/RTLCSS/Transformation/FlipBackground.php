<?php
namespace MoodleHQ\RTLCSS\Transformation;

use MoodleHQ\RTLCSS\Transformation\Value\TransformableStringValue;
use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\Value\CSSFunction;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Size;

/**
 * Flips values of background
 */
class FlipBackground implements TransformationInterface
{
    /**
     * @inheritDoc
     */
    public function appliesFor($property)
    {
        return (preg_match('/background(-position(-x)?|-image)?$/i', $property) === 1);
    }

    /**
     * @inheritDoc
     */
    public function transform(Rule $rule)
    {
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
                    $flipped = (new TransformableStringValue($part))
                        ->swapLeftRight()
                        ->toString();

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
}
