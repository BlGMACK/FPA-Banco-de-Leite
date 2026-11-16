<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InternalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public function test_public_pages_offer_donation_without_account_and_no_registration(): void
    {
        $this->get('/')->assertOk()->assertSee('sem criar uma conta')->assertSee(route('solicitacoes.create'));
        $this->get('/login')->assertOk()->assertSee('Acesso da equipe')->assertDontSee('/register', false);
        $this->get('/doar')->assertOk();
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Intruso', 'email' => 'intruso@example.test', 'role' => 'admin', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_collection_preview_exists_only_in_local_environment_and_saves_nothing(): void
    {
        $this->get('/coletas')->assertNotFound();

        $this->app['env'] = 'local';

        $this->get('/coletas')->assertOk()
            ->assertSee('Registrar coleta e recebimento')
            ->assertSee('Nenhum dado será salvo nesta tela.')
            ->assertDontSee('<form', false);
    }

    public function test_removed_account_cannot_login_or_recover_password_even_with_existing_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $user->delete();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'role' => 'admin', 'remember' => '1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasErrors('email');
        Notification::assertNothingSent();
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[DataProvider('privateRoutes')]
    public function test_removed_account_session_is_ended_before_accessing_private_routes(string $method, string $url): void
    {
        $user = User::factory()->create();
        User::whereKey($user->id)->delete();
        $this->actingAs($user)->$method($url)->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public static function privateRoutes(): array
    {
        return [['get', '/dashboard'], ['get', '/profile'], ['patch', '/profile'], ['delete', '/profile'],
            ['put', '/password'], ['get', '/verify-email'], ['get', '/interno/agenda'], ['get', '/interno/solicitacoes']];
    }

    public function test_internal_role_is_rechecked_after_session_was_created(): void
    {
        $user = User::factory()->funcionario()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        User::whereKey($user->id)->delete();
        $this->get('/profile')->assertForbidden();
        $this->assertGuest();
    }

    public function test_employee_and_admin_can_sign_in(): void
    {
        foreach (['funcionario', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->post('/logout')->assertRedirect('/');
        }
    }

    public function test_side_menu_shows_account_with_profile_and_logout(): void
    {
        $funcionaria = User::factory()->funcionario()->create(['name' => 'Ana <b>Paula</b> Siqueira', 'email' => 'ana@exemplo.com']);

        $this->actingAs($funcionaria)->get('/dashboard')->assertOk()
            ->assertSeeInOrder(['class="blh-conta"', 'Ana &lt;b&gt;Paula&lt;/b&gt; Siqueira', 'ana@exemplo.com', 'Equipe',
                route('profile.edit'), 'action="'.route('logout').'"', 'Navegação principal'], false)
            ->assertSee('<span class="blh-conta__avatar" aria-hidden="true">AS</span>', false)
            ->assertDontSee('blh-topo__usuario', false);
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertSee('Administração');
    }

    public function test_demo_routes_are_not_available(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin-teste')->assertNotFound();
        $this->get('/interno-teste')->assertNotFound();

    }
}
