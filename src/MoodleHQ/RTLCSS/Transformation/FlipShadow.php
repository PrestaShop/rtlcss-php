<?php
namespace MoodleHQ\RTLCSS\Transformation;

use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\Value\RuleValueList;

class FlipShadow implements TransformationInterface
{
    /**
     * @inheritDoc
     */
    public function appliesFor($property)
    {
        return (preg_match('/shadow/i', $property) === 1);
    }

    /**
     * @inheritDoc
     */
    public function transform(Rule $rule)
    {
        $value = $rule->getValue();

        // TODO Fix upstream, each shadow should be in a RuleValueList.
        if ($value instanceof RuleValueList) {
            // negate($value->getListComponents()[0]);
        }
    }
}
