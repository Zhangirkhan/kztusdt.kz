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

    private const VALID_IIN = '900101100014';

    private const VALID_IBAN = 'KZ12 3456 7890 1234 5678';

    public function test_user_can_create_card_with_required_fields(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)->post('/ru/profile/bank/cards', [
            'bank_code' => 'kaspi',
            'bik' => 'CASPKZKA',
            'holder_name' => 'Иванов Иван',
            'iin' => self::VALID_IIN,
            'iban' => self::VALID_IBAN,
        ])->assertRedirect(route('profile.bank'));

        $card = UserBankCard::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('kaspi', $card->bank_code);
        $this->assertSame('CASPKZKA', $card->bik);
        $this->assertSame('Kaspi Bank', $card->label);
        $this->assertSame('Иванов Иван', $card->holder_name);
        $this->assertSame('KZ123456789012345678', $card->iban);
        $this->assertSame(self::VALID_IIN, $user->fresh()->iin);
    }

    public function test_card_requires_iban_bik_and_iin(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)->post('/ru/profile/bank/cards', [
            'bank_code' => 'halyk',
            'bik' => 'INVALID',
            'holder_name' => 'Тест',
            'iin' => '123',
            'iban' => '',
        ])->assertSessionHasErrors(['bik', 'iin', 'iban']);

        $this->assertSame(0, UserBankCard::query()->count());
    }

    public function test_user_can_rename_update_and_delete_card(): void
    {
        $user = $this->createClient();
        $card = $user->bankCards()->create([
            'bank_code' => 'bcc',
            'bik' => 'KCJBKZKX',
            'label' => 'Старое',
            'holder_name' => 'Петров',
            'phone' => null,
            'iban' => 'KZ861234567890123456',
        ]);

        $this->actingAs($user)->patch("/ru/profile/bank/cards/{$card->id}/rename", [
            'label' => 'Зарплатная',
        ])->assertRedirect(route('profile.bank'));

        $this->assertSame('Зарплатная', $card->fresh()->label);

        $this->actingAs($user)->patch("/ru/profile/bank/cards/{$card->id}", [
            'bank_code' => 'freedom',
            'bik' => 'KSNVKZKA',
            'holder_name' => 'Петров Пётр',
            'iban' => 'KZ86 1234 5678 9012 3456',
        ])->assertRedirect(route('profile.bank'));

        $card->refresh();
        $this->assertSame('freedom', $card->bank_code);
        $this->assertSame('KSNVKZKA', $card->bik);
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
            'bik' => 'ATYNKZKA',
            'label' => 'Чужая',
            'holder_name' => 'Владелец',
            'phone' => null,
            'iban' => 'KZ112233445566778899',
        ]);

        $this->actingAs($other)
            ->patch("/ru/profile/bank/cards/{$card->id}", [
                'holder_name' => 'Хакер',
                'bank_code' => 'altyn',
                'bik' => 'ATYNKZKA',
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
            'bik' => 'CASPKZKA',
            'label' => 'Основная',
            'holder_name' => 'Иван',
            'phone' => null,
            'iban' => 'KZ123456789012345678',
        ]);

        $this->actingAs($user)
            ->get('/ru/profile/bank')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Bank')
                ->has('banks', 7)
                ->has('cards', 1)
                ->where('cards.0.label', 'Основная')
                ->where('banks.0.code', 'kaspi')
                ->where('banks.0.bik', 'CASPKZKA'));
    }

    public function test_duplicate_iban_for_same_bank_is_rejected(): void
    {
        $user = $this->createClient();
        $user->bankCards()->create([
            'bank_code' => 'kaspi',
            'bik' => 'CASPKZKA',
            'label' => 'Первая',
            'holder_name' => 'Иван',
            'phone' => null,
            'iban' => 'KZ123456789012345678',
        ]);

        $this->actingAs($user)->post('/ru/profile/bank/cards', [
            'bank_code' => 'kaspi',
            'bik' => 'CASPKZKA',
            'holder_name' => 'Иван',
            'iin' => self::VALID_IIN,
            'iban' => 'KZ12 3456 7890 1234 5678',
        ])->assertSessionHasErrors(['iban']);
    }

    public function test_bik_must_match_selected_bank(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)->post('/ru/profile/bank/cards', [
            'bank_code' => 'kaspi',
            'bik' => 'HSBKKZKX',
            'holder_name' => 'Иван',
            'iin' => self::VALID_IIN,
            'iban' => self::VALID_IBAN,
        ])->assertSessionHasErrors(['bik']);
    }

    public function test_legacy_requisites_migrate_into_cards(): void
    {
        $user = $this->createClient([
            'bank_name' => 'Kaspi Bank',
            'bank_holder' => 'Сидоров',
            'bank_account' => 'KZ55 6677 8899 0011 2233',
        ]);

        $normalizedIban = 'KZ556677889900112233';
        UserBankCard::query()->create([
            'user_id' => $user->id,
            'bank_code' => 'kaspi',
            'bik' => 'CASPKZKA',
            'label' => 'Kaspi Bank',
            'holder_name' => 'Сидоров',
            'phone' => null,
            'iban' => $normalizedIban,
        ]);

        $this->assertSame(1, $user->bankCards()->count());
        $this->assertSame($normalizedIban, $user->bankCards()->first()->iban);
    }
}
