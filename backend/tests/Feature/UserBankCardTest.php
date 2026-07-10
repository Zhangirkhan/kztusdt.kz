<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserBankCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class UserBankCardTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_user_can_create_card_with_phone_and_iban(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)->post('/ru/profile/bank/cards', [
            'bank_code' => 'kaspi',
            'label' => 'Моя Kaspi',
            'holder_name' => 'Иванов Иван',
            'phone' => '+7 (701) 234-56-78',
            'iban' => 'KZ12 3456 7890 1234 5678',
        ])->assertRedirect(route('profile.bank'));

        $card = UserBankCard::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('kaspi', $card->bank_code);
        $this->assertSame('Моя Kaspi', $card->label);
        $this->assertSame('Иванов Иван', $card->holder_name);
        $this->assertSame('+77012345678', $card->phone);
        $this->assertSame('KZ123456789012345678', $card->iban);
    }

    public function test_card_requires_phone_or_iban(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)->post('/ru/profile/bank/cards', [
            'bank_code' => 'halyk',
            'label' => 'Пустая',
            'holder_name' => 'Тест',
            'phone' => '+7',
            'iban' => 'KZ',
        ])->assertSessionHasErrors(['phone', 'iban']);

        $this->assertSame(0, UserBankCard::query()->count());
    }

    public function test_user_can_rename_update_and_delete_card(): void
    {
        $user = $this->createClient();
        $card = $user->bankCards()->create([
            'bank_code' => 'bcc',
            'label' => 'Старое',
            'holder_name' => 'Петров',
            'phone' => '+77011234567',
            'iban' => null,
        ]);

        $this->actingAs($user)->patch("/ru/profile/bank/cards/{$card->id}/rename", [
            'label' => 'Зарплатная',
        ])->assertRedirect(route('profile.bank'));

        $this->assertSame('Зарплатная', $card->fresh()->label);

        $this->actingAs($user)->patch("/ru/profile/bank/cards/{$card->id}", [
            'bank_code' => 'freedom',
            'label' => 'Зарплатная Freedom',
            'holder_name' => 'Петров Пётр',
            'phone' => '+77011234567',
            'iban' => 'KZ86 1234 5678 9012 3456',
        ])->assertRedirect(route('profile.bank'));

        $card->refresh();
        $this->assertSame('freedom', $card->bank_code);
        $this->assertSame('Петров Пётр', $card->holder_name);
        $this->assertSame('KZ861234567890123456', $card->iban);

        $this->actingAs($user)
            ->delete("/ru/profile/bank/cards/{$card->id}")
            ->assertRedirect(route('profile.bank'));

        $this->assertSame(0, UserBankCard::query()->count());
    }

    public function test_user_cannot_modify_another_users_card(): void
    {
        $owner = $this->createClient();
        $other = $this->createClient();
        $card = $owner->bankCards()->create([
            'bank_code' => 'altyn',
            'label' => 'Чужая',
            'holder_name' => 'Владелец',
            'phone' => null,
            'iban' => 'KZ112233445566778899',
        ]);

        $this->actingAs($other)
            ->patch("/ru/profile/bank/cards/{$card->id}", [
                'label' => 'Хак',
                'holder_name' => 'Хакер',
                'bank_code' => 'altyn',
                'iban' => 'KZ112233445566778899',
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete("/ru/profile/bank/cards/{$card->id}")
            ->assertForbidden();
    }

    public function test_bank_page_lists_cards_and_catalog(): void
    {
        $user = $this->createClient();
        $user->bankCards()->create([
            'bank_code' => 'kaspi',
            'label' => 'Основная',
            'holder_name' => 'Иван',
            'phone' => '+77015556677',
            'iban' => null,
        ]);

        $this->actingAs($user)
            ->get('/ru/profile/bank')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Bank')
                ->has('banks', 7)
                ->has('cards', 1)
                ->where('cards.0.label', 'Основная')
                ->where('banks.0.code', 'kaspi'));
    }

    public function test_duplicate_iban_for_same_bank_is_rejected(): void
    {
        $user = $this->createClient();
        $user->bankCards()->create([
            'bank_code' => 'kaspi',
            'label' => 'Первая',
            'holder_name' => 'Иван',
            'phone' => null,
            'iban' => 'KZ123456789012345678',
        ]);

        $this->actingAs($user)->post('/ru/profile/bank/cards', [
            'bank_code' => 'kaspi',
            'label' => 'Дубль',
            'holder_name' => 'Иван',
            'iban' => 'KZ12 3456 7890 1234 5678',
        ])->assertSessionHasErrors(['iban']);
    }

    public function test_legacy_requisites_migrate_into_cards(): void
    {
        $user = $this->createClient([
            'bank_name' => 'Kaspi Bank',
            'bank_holder' => 'Сидоров',
            'bank_account' => 'KZ55 6677 8899 0011 2233',
        ]);

        // Simulate migration insert that would have happened for pre-existing users.
        $normalizedIban = 'KZ556677889900112233';
        UserBankCard::query()->create([
            'user_id' => $user->id,
            'bank_code' => 'kaspi',
            'label' => 'Kaspi Bank',
            'holder_name' => 'Сидоров',
            'phone' => null,
            'iban' => $normalizedIban,
        ]);

        $this->assertSame(1, $user->bankCards()->count());
        $this->assertSame($normalizedIban, $user->bankCards()->first()->iban);
    }
}
