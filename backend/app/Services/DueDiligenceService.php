<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DueDiligenceProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DueDiligenceService
{
    public function threshold(): float
    {
        return (float) config('due_diligence.threshold_usdt', 10000);
    }

    public function exceedsThreshold(float|string $amount): bool
    {
        return bccomp(
            number_format((float) $amount, 8, '.', ''),
            number_format($this->threshold(), 8, '.', ''),
            8,
        ) >= 0;
    }

    public function hasSubmittedProfile(User $user): bool
    {
        if ($user->relationLoaded('dueDiligenceProfile') && $user->dueDiligenceProfile !== null) {
            return true;
        }

        return $user->dueDiligenceProfile()->exists();
    }

    public function requiresBlockingQuestionnaire(User $user): bool
    {
        return $user->due_diligence_required_at !== null
            && ! $this->hasSubmittedProfile($user);
    }

    public function requiresQuestionnaireForWithdrawal(User $user, float|string $amount): bool
    {
        return $this->exceedsThreshold($amount) && ! $this->hasSubmittedProfile($user);
    }

    public function markRequired(User $user): void
    {
        if ($this->hasSubmittedProfile($user) || $user->due_diligence_required_at !== null) {
            return;
        }

        $user->forceFill(['due_diligence_required_at' => now()])->save();
    }

    /**
     * @param  array{
     *     source_of_funds: string,
     *     source_of_funds_other?: string|null,
     *     occupation: string,
     *     industry: string,
     *     industry_other?: string|null,
     *     annual_income: string,
     *     platform_purpose: string,
     *     platform_purpose_other?: string|null,
     * }  $data
     */
    public function submit(User $user, array $data): DueDiligenceProfile
    {
        return DB::transaction(function () use ($user, $data): DueDiligenceProfile {
            $profile = DueDiligenceProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    ...$data,
                    'submitted_at' => now(),
                ],
            );

            $user->forceFill(['due_diligence_required_at' => null])->save();

            return $profile;
        });
    }
}
