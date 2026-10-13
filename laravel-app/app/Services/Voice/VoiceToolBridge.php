<?php

namespace App\Services\Voice;

use App\Services\Assistant\AssistantToolRegistry;

/**
 * Voice may call only names that already exist on the MAI tool registry.
 * Caller ID never raises sensitivity. Verified and owner tools stay closed.
 */
class VoiceToolBridge
{
    protected $registry;

    public function __construct(AssistantToolRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function run($name, array $params, array $context)
    {
        $decision = $this->decide($name, $context);
        if (! $decision['allowed']) {
            return [
                'allowed' => false,
                'name' => (string) $name,
                'reason' => $decision['reason'],
                'result' => null,
            ];
        }

        $runner = config('voice.tool_runner');
        $result = null;
        if (is_callable($runner)) {
            $result = call_user_func($runner, (string) $name, $params, $context);
        }

        return [
            'allowed' => true,
            'name' => (string) $name,
            'reason' => 'allowed',
            'result' => $result,
        ];
    }

    public function decide($name, array $context)
    {
        $name = (string) $name;
        $blocked = ['sql', 'query', 'shell', 'filesystem', 'eval'];
        foreach ($blocked as $needle) {
            if (stripos($name, $needle) !== false) {
                return ['allowed' => false, 'reason' => 'blocked_name'];
            }
        }

        $meta = $this->registry->get($name);
        if (! $meta) {
            return ['allowed' => false, 'reason' => 'unknown_tool'];
        }

        $sensitivity = isset($meta['sensitivity']) ? (string) $meta['sensitivity'] : 'OWNER';
        if ($sensitivity === 'VERIFIED' || $sensitivity === 'OWNER') {
            return ['allowed' => false, 'reason' => 'sensitivity'];
        }
        if ($sensitivity === 'RECOGNIZED' && empty($context['verified'])) {
            return ['allowed' => false, 'reason' => 'not_verified'];
        }

        return ['allowed' => true, 'reason' => 'allowed'];
    }
}
