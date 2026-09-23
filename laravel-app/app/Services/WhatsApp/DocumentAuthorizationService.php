<?php

namespace App\Services\WhatsApp;

class DocumentAuthorizationService
{
    public function decide($identityType, $identityId, $ownerType, $ownerId, array $definition, $session)
    {
        if ((string) $identityType !== (string) $ownerType || (int) $identityId !== (int) $ownerId || (int) $identityId < 1) {
            return ['allowed' => false, 'code' => 'wrong_owner'];
        }
        if (isset($definition['sensitivity']) && $definition['sensitivity'] === 'PRIVILEGED') {
            return ['allowed' => false, 'code' => 'privileged'];
        }
        if (! empty($definition['otp'])) {
            $scope = isset($definition['scope']) ? $definition['scope'] : '';
            if (! $session || ! $session->isActive() || (string) $session->purpose !== (string) $scope) {
                return ['allowed' => false, 'code' => 'verification_required'];
            }
            if ((string) $session->identity_type !== (string) $identityType || (int) $session->identity_id !== (int) $identityId) {
                return ['allowed' => false, 'code' => 'wrong_owner'];
            }
        }

        return ['allowed' => true, 'code' => null];
    }
}
