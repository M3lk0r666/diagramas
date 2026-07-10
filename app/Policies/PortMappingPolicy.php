<?php

namespace App\Policies;

use App\Models\PortMapping;
use App\Models\User;

/**
 * Policy de autorización: cada usuario solo accede a sus propios mapeos.
 * Auto-descubierta por Laravel (convenio PortMapping → PortMappingPolicy).
 */
class PortMappingPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, PortMapping $portMapping): bool
    {
        return $user->id === $portMapping->user_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, PortMapping $portMapping): bool
    {
        return $user->id === $portMapping->user_id;
    }

    public function delete(User $user, PortMapping $portMapping): bool
    {
        return $user->id === $portMapping->user_id;
    }
}
