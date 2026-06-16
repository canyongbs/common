<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class TestWithoutPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $model): bool
    {
        return true;
    }

    public function delete(User $user, Model $model): bool
    {
        return true;
    }

    public function deleteAny(User $user, Model $model): bool
    {
        return true;
    }

    public function restore(User $user, Model $model): bool
    {
        return true;
    }

    public function restoreAny(User $user, Model $model): bool
    {
        return true;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return true;
    }

    public function forceDeleteAny(User $user, Model $model): bool
    {
        return true;
    }
}