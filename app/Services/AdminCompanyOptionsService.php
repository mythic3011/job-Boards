<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AdminCompanyOptionsService
{
    private const CACHE_KEY = 'admin.company_filter_options.v1';
    private const CACHE_TTL_MINUTES = 5;

    /**
     * @return Collection<int, User>
     */
    public function getCompanyOptions(): Collection
    {
        /** @var Collection<int, User> $companies */
        $companies = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            static fn (): Collection => User::query()
                ->where('user_type', 'company')
                ->orderBy('nickname')
                ->get(['id', 'nickname'])
        );

        return $companies;
    }
}

