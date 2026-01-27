<?php

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class CustomUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $model = $this->createModel();

        // Ensure we're working with string UUIDs
        $identifier = (string) $identifier;

        return $this->newModelQuery($model)
            ->where($model->getKeyName(), $identifier)
            ->first();
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $model = $this->createModel();

        // Ensure we're working with string UUIDs
        $identifier = (string) $identifier;

        $retrievedModel = $this->newModelQuery($model)
            ->where($model->getKeyName(), $identifier)
            ->first();

        if (!$retrievedModel) {
            return null;
        }

        $rememberToken = $retrievedModel->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token)
            ? $retrievedModel : null;
    }
}