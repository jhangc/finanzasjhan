<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Regla única del sistema: un usuario solo ve y toca sus propios registros.
 */
abstract class OwnedResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    protected function owns(User $user, Model $model): bool
    {
        return (int) $model->getAttribute('user_id') === (int) $user->getKey();
    }
}
