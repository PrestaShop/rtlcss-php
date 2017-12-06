<?php
namespace MoodleHQ\RTLCSS\Transformation;

use Sabberworm\CSS\Rule\Rule;

/**
 * Flips values of transform
 */
class FlipTransform implements TransformationInterface
{
    /**
     * @inheritDoc
     */
    public function appliesFor($property)
    {
        return (preg_match('/^(?!text\-).*?transform$/i', $property) === 1);
    }

    /**
     * @inheritDoc
     */
    public function transform(Rule $rule)
    {
        // TODO Parse function parameters first.
    }
}
