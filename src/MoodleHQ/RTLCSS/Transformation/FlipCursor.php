<?php
namespace MoodleHQ\RTLCSS\Transformation;

use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\Value\RuleValueList;

/**
 * Flips values of cursor
 */
class FlipCursor implements TransformationInterface
{
    /**
     * @inheritDoc
     */
    public function appliesFor($property)
    {
        return (preg_match('/cursor/i', $property) === 1);
    }

    /**
     * @inheritDoc
     */
    public function transform(Rule $rule)
    {
        $value = $rule->getValue();

        $hasList = false;

        $parts = [$value];
        if ($value instanceof RuleValueList) {
            $hastList = true;
            $parts = $value->getListComponents();
        }

        foreach ($parts as $key => $part) {
            if (!is_object($part)) {
                $parts[$key] = preg_replace_callback('/\b(ne|nw|se|sw|nesw|nwse)-resize/', function($matches) {
                    return str_replace($matches[1], str_replace(['e', 'w', '*'], ['*', 'e', 'w'], $matches[1]), $matches[0]);
                }, $part);
            }
        }

        if ($hasList) {
            $value->setListComponents($parts);
        } else {
            $rule->setValue($parts[0]);
        }
    }

}
