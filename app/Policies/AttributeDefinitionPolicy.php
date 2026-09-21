<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Catalog\Models\AttributeDefinition;
use App\Domain\Identity\Models\User;

class AttributeDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attributes.manage');
    }

    public function view(User $user, AttributeDefinition $attributeDefinition): bool
    {
        return $user->can('attributes.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('attributes.manage');
    }

    public function update(User $user, AttributeDefinition $attributeDefinition): bool
    {
        return $user->can('attributes.manage');
    }

    public function delete(User $user, AttributeDefinition $attributeDefinition): bool
    {
        return $user->can('attributes.manage');
    }
}
