<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KycProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Этап 2: клиентская часть KYC — анкета и загрузка документов.
 */
final class KycSubmissionTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/kyc')->assertRedirect('/auth/phone');
    }

    public function test_user_without_verified_phone_cannot_open_kyc(): void
    {
        $user = $this->createUnverifiedClient(['phone_verified' => false]);

        $this->actingAs($user)->get('/kyc')->assertForbidden();
    }

    public function test_kyc_page_renders_manual_provider(): void
    {
        $user = $this->createUnverifiedClient();

        $this->actingAs($user)
            ->get('/kyc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Kyc')
                ->where('provider', 'manual')
                ->where('kycStatus', 'none'));
    }

    public function test_client_submits_kyc_with_documents(): void
    {
        Storage::fake('local');

        $user = $this->createUnverifiedClient();

        $response = $this->actingAs($user)->post('/kyc', [
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'document_type' => 'id_card',
            'document_number' => '123456789',
            'id_front' => UploadedFile::fake()->image('front.jpg'),
            'id_back' => UploadedFile::fake()->image('back.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertRedirect(route('kyc'));

        $profile = KycProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('pending_review', $profile->status);
        $this->assertSame('Иван', $profile->first_name);
        $this->assertSame('pending_review', $user->fresh()->kyc_status);
        $this->assertCount(3, $profile->documents);

        foreach ($profile->documents as $document) {
            Storage::disk('local')->assertExists($document->file_path);
        }

        $this->assertDatabaseHas('manual_approvals', [
            'entity_type' => 'kyc_profile',
            'entity_id' => $profile->id,
            'status' => 'pending',
            'required_role' => 'security_officer',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'kyc.submitted',
            'user_id' => $user->id,
        ]);
    }

    public function test_submission_requires_all_three_documents(): void
    {
        $user = $this->createUnverifiedClient();

        $this->actingAs($user)->post('/kyc', [
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'document_type' => 'id_card',
            'document_number' => '123456789',
        ])->assertSessionHasErrors(['id_front', 'id_back', 'selfie']);
    }

    public function test_document_type_is_validated(): void
    {
        Storage::fake('local');

        $user = $this->createUnverifiedClient();

        $this->actingAs($user)->post('/kyc', [
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'document_type' => 'driving_license',
            'document_number' => '123456789',
            'id_front' => UploadedFile::fake()->image('front.jpg'),
            'id_back' => UploadedFile::fake()->image('back.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertSessionHasErrors(['document_type']);
    }

    public function test_cannot_resubmit_while_pending_review(): void
    {
        Storage::fake('local');

        $user = $this->createUnverifiedClient(['kyc_status' => 'pending_review']);

        $this->actingAs($user)->post('/kyc', $this->validPayload())
            ->assertSessionHasErrors(['form']);
    }

    public function test_cannot_resubmit_after_approval(): void
    {
        Storage::fake('local');

        $user = $this->createClient(); // kyc_status = approved

        $this->actingAs($user)->post('/kyc', $this->validPayload())
            ->assertSessionHasErrors(['form']);
    }

    public function test_rejected_user_can_resubmit(): void
    {
        Storage::fake('local');

        $user = $this->createUnverifiedClient(['kyc_status' => 'rejected']);

        KycProfile::query()->create([
            'user_id' => $user->id,
            'status' => 'rejected',
            'rejection_reason' => 'Размытое фото',
        ]);

        $this->actingAs($user)->post('/kyc', $this->validPayload())
            ->assertRedirect(route('kyc'));

        $profile = KycProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending_review', $profile->status);
        $this->assertNull($profile->rejection_reason);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'document_type' => 'passport',
            'document_number' => 'N12345678',
            'id_front' => UploadedFile::fake()->image('front.jpg'),
            'id_back' => UploadedFile::fake()->image('back.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ];
    }
}
