<?php

namespace NextDeveloper\AI\Services;

use NextDeveloper\AI\Services\AbstractServices\AbstractLlmBroadcastGroupsService;

/**
 * This class is responsible from managing the data for LlmBroadcastGroups
 *
 * Class LlmBroadcastGroupsService.
 *
 * @package NextDeveloper\AI\Database\Models
 */
class LlmBroadcastGroupsService extends AbstractLlmBroadcastGroupsService
{
    public static function create(array $data)
    {
        self::assertTargets($data['targets'] ?? []);

        return parent::create($data);
    }

    public static function update($id, array $data)
    {
        if (array_key_exists('targets', $data)) {
            self::assertTargets($data['targets']);
        }

        return parent::update($id, $data);
    }

    /**
     * Every target must be either {"alias": "..."} or {"provider": "...", "model": "..."}.
     */
    private static function assertTargets(array $targets): void
    {
        foreach ($targets as $target) {
            $valid = !empty($target['alias']) || (!empty($target['provider']) && !empty($target['model']));

            throw_if(
                !$valid,
                new \InvalidArgumentException('Each target needs an "alias" or both "provider" and "model".')
            );
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
