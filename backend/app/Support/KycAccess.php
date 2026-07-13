<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Support\LocaleManager;
use Illuminate\Http\RedirectResponse;

final class KycAccess
{
    public static function isApproved(User $user): bool
    {
        return $user->canUseWallet();
    }

    public static function denyResponse(User $user, string $feature = 'default'): ?RedirectResponse
    {
        if (self::isApproved($user)) {
            return null;
        }

        if (! $user->phone_verified) {
            return redirect()->route('auth.phone', [
                'locale' => LocaleManager::resolve(request()),
            ])
                ->withErrors(['phone' => 'Подтвердите номер телефона, чтобы продолжить.']);
        }

        return redirect()->route('kyc', [
            'locale' => LocaleManager::resolve(request()),
        ])
            ->withErrors(['form' => self::message($user, $feature)]);
    }

    public static function message(User $user, string $feature = 'default'): string
    {
        if ($user->kyc_status === 'approved' && $user->hasIinMismatch()) {
            return match ($feature) {
                'wallet' => 'ИИН из регистрации не совпадает с KYC. Укажите корректный ИИН.',
                'history' => 'ИИН из регистрации не совпадает с KYC. Укажите корректный ИИН.',
                'exchange' => 'ИИН из регистрации не совпадает с KYC. Укажите корректный ИИН.',
                'withdraw' => 'ИИН из регистрации не совпадает с KYC. Укажите корректный ИИН.',
                'bank' => 'ИИН из регистрации не совпадает с KYC. Укажите корректный ИИН.',
                default => 'ИИН из регистрации не совпадает с KYC. Укажите корректный ИИН.',
            };
        }

        if ($user->kyc_status === 'pending_review') {
            return match ($feature) {
                'wallet' => 'Кошелёк откроется после одобрения KYC. Заявка уже на проверке.',
                'history' => 'История операций доступна после одобрения KYC. Заявка уже на проверке.',
                'exchange' => 'Обмен USDT доступен после одобрения KYC. Заявка уже на проверке.',
                'withdraw' => 'Вывод USDT доступен после одобрения KYC. Заявка уже на проверке.',
                'bank' => 'Банковские реквизиты доступны после одобрения KYC. Заявка уже на проверке.',
                default => 'Раздел доступен после одобрения KYC. Заявка уже на проверке.',
            };
        }

        if ($user->kyc_status === 'rejected') {
            return match ($feature) {
                'wallet' => 'Пройдите KYC заново, чтобы открыть кошелёк USDT.',
                'history' => 'Пройдите KYC заново, чтобы открыть историю операций.',
                'exchange' => 'Пройдите KYC заново, чтобы пользоваться обменом.',
                'withdraw' => 'Пройдите KYC заново, чтобы выводить USDT.',
                'bank' => 'Пройдите KYC заново, чтобы указать банковские реквизиты.',
                default => 'Пройдите KYC-верификацию, чтобы открыть этот раздел.',
            };
        }

        return match ($feature) {
            'wallet' => 'Пройдите KYC-верификацию, чтобы открыть кошелёк USDT.',
            'history' => 'Пройдите KYC-верификацию, чтобы открыть историю операций.',
            'exchange' => 'Пройдите KYC-верификацию, чтобы пользоваться обменом USDT / KZT.',
            'withdraw' => 'Пройдите KYC-верификацию, чтобы выводить USDT.',
            'bank' => 'Пройдите KYC-верификацию, чтобы указать банковские реквизиты.',
            default => 'Пройдите KYC-верификацию, чтобы открыть этот раздел.',
        };
    }
}
