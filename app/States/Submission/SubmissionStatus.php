<?php

namespace App\States\Submission;

use Spatie\ModelStates\State;

abstract class SubmissionStatus extends State
{
    abstract public function label(): string;

    public function key(): string
    {
        return $this->getValue();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        /** @var array<string, string> $options */
        $options = [];

        foreach (static::all() as $stateClass) {
            /** @var class-string<SubmissionStatus> $stateClass */
            /** @var SubmissionStatus $instance */
            $instance = new $stateClass(new \App\Models\FormSubmission());
            $options[$instance->key()] = $instance->label();
        }

        return $options;
    }
}
