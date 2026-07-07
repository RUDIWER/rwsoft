<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_switch_locale(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->post(route('locale.update'), [
                'locale' => 'fr',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('fr', session('locale'));
    }

    public function test_locale_switch_rejects_unknown_locale(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->post(route('locale.update'), [
                'locale' => 'zz',
            ]);

        $response
            ->assertSessionHasErrors('locale')
            ->assertRedirect('/profile');
    }

    public function test_inertia_shared_locale_and_rwtable_translations_follow_session_locale(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['locale' => 'fr'])
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('"locale":"fr"', false)
            ->assertSee('"yes":"Oui"', false)
            ->assertSee('"required":"Cette variable est obligatoire."', false);
    }
}
