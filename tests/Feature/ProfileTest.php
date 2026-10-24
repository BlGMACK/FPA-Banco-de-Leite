<?php

namespace Tests\Feature;

use App\Models\ConcessaoPermissao;
use App\Models\ContatoSolicitacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public function test_account_with_contact_history_cannot_be_deleted_and_remains_authenticated(): void
    {
        $user = User::factory()->funcionario()->create();
        $contact = ContatoSolicitacao::factory()->for($user, 'registradoPor')->create();

        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('userDeletion', [
                'password' => 'Esta conta possui registros vinculados e não pode ser excluída. Procure a administração.',
            ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
        $this->assertDatabaseHas('contatos_solicitacao', ['id' => $contact->id, 'registrado_por_user_id' => $user->id]);
    }

    public function test_revoked_permission_history_also_prevents_account_deletion(): void
    {
        $grant = ConcessaoPermissao::factory()->create(['revogada_em' => now()]);
        $user = $grant->usuario;

        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
        $this->assertDatabaseHas('permissao_user', ['id' => $grant->id, 'user_id' => $user->id]);
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
