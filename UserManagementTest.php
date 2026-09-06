<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function authorizationFor(User $user): array
    {
        $token = 'test-token-'.$user->id;
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_admin_can_list_and_filter_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'identifier' => 'ADM100']);
        User::factory()->create(['role' => 'dosen', 'identifier' => 'DSN100', 'name' => 'Dosen Contoh']);
        User::factory()->create(['role' => 'mahasiswa', 'identifier' => 'MHS100']);

        $response = $this->withHeaders($this->authorizationFor($admin))
            ->getJson('/api/users?role=dosen&search=Dosen');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.identifier', 'DSN100')
            ->assertJsonMissingPath('data.0.api_token');
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $student = User::factory()->create(['role' => 'mahasiswa', 'identifier' => 'MHS200']);

        $this->withHeaders($this->authorizationFor($student))
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'identifier' => 'ADM300']);

        $response = $this->withHeaders($this->authorizationFor($admin))
            ->postJson('/api/users', [
                'name' => 'Mahasiswa Baru',
                'email' => 'baru@gmail.com',
                'identifier' => 'MHS300',
                'role' => 'mahasiswa',
                'program_studi' => 'Informatika',
                'password' => 'password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.identifier', 'MHS300');

        $this->assertDatabaseHas('users', [
            'email' => 'baru@gmail.com',
            'role' => 'mahasiswa',
        ]);
    }
}
