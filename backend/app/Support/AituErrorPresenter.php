<?php

declare(strict_types=1);

namespace App\Support;

final class AituErrorPresenter
{
    public static function callbackMessage(string $error, string $description = ''): string
    {
        $normalized = mb_strtolower(trim($description));

        if ($error === 'invalid_request' && str_contains($normalized, 'client_id')) {
            return 'Сервис Aitu Passport не принял client_id. Проверьте, что AITU_CLIENT_ID и AITU_BASE_URL '
                .'относятся к одной среде (тест: passport.test.supreme-team.tech, прод: passport.aitu.io).';
        }

        if ($error === 'access_denied') {
            return 'Вы отменили верификацию в Aitu Passport.';
        }

        if (str_contains($normalized, 'invalid_scope') || str_contains($normalized, 'not_allowed_scopes')) {
            return 'Запрошенный scope не подключён к вашему client_id в Aitu Passport. '
                .'Уберите CONFIDENCE_LEVEL из AITU_SCOPE или запросите доступ у менеджера Aitu '
                .'и укажите его в AITU_KYC_SCOPE.';
        }

        if (str_contains($normalized, 'base64')) {
            return 'Aitu Passport не принял подпись ИИН (iin_signature). '
                .'Отключите AITU_IIN_SIGNING_ENABLED или проверьте публичный RSA-ключ в консоли Aitu.';
        }

        if (str_contains($normalized, 'userdatavalidatorid')) {
            return 'В консоли Aitu указан валидатор данных (userdatavalidatorid), который не зарегистрирован '
                .'на стороне Aitu. Попросите менеджера Aitu активировать валидатор с ID '
                .config('aitu.validation.validator_id')
                .' или временно отключите «Валидацию на стороне клиента» в консоли. '
                .'До этого оставьте AITU_IIN_SIGNING_ENABLED=false.';
        }

        if (str_contains($normalized, 'scope')) {
            return 'Aitu Passport отклонил список scope. Проверьте AITU_SCOPE и AITU_KYC_SCOPE в .env.';
        }

        if ($description !== '') {
            return 'Aitu Passport: '.$description;
        }

        return 'Верификация через Aitu Passport не завершена.';
    }
}
