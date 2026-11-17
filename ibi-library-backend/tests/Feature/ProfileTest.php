<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_name(): void
    {
        $user = User::query()->create([
            'nama' => 'Nama Lama',
            'email' => 'user-profile@example.test',
            'kata_sandi' => 'password123',
            'peran' => 'anggota',
            'status_verifikasi' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profil', ['nama' => 'Nama Baru']);
        $response->assertOk()
            ->assertJsonPath('user.nama', 'Nama Baru');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $user->id_pengguna,
            'nama' => 'Nama Baru',
        ]);
    }

    public function test_user_can_request_otp_and_change_password(): void
    {
        $user = User::query()->create([
            'nama' => 'User Sandi',
            'email' => 'user-sandi@example.test',
            'kata_sandi' => 'passwordLama123',
            'peran' => 'anggota',
            'status_verifikasi' => true,
        ]);

        Sanctum::actingAs($user);

        // 1. Minta OTP
        $otpResponse = $this->postJson('/api/profil/minta-otp-sandi')
            ->assertOk()
            ->assertJsonStructure(['otp', 'email', 'message']);

        $otp = $otpResponse->json('otp');
        $this->assertNotEmpty($otp);
        $this->assertSame($otp, Cache::get('password_otp_'.$user->id_pengguna));

        // 2. Coba ganti dengan OTP salah -> gagal 422
        $this->postJson('/api/profil/ubah-sandi', [
            'otp' => '999999',
            'kata_sandi' => 'passwordBaru123',
        ])->assertUnprocessable()->assertJsonValidationErrors('otp');

        // 3. Ganti dengan OTP benar -> berhasil 200
        $this->postJson('/api/profil/ubah-sandi', [
            'otp' => $otp,
            'kata_sandi' => 'passwordBaru123',
        ])->assertOk()->assertJsonPath('message', 'Kata sandi berhasil diperbarui di database.');

        // OTP cache dibersihkan
        $this->assertNull(Cache::get('password_otp_'.$user->id_pengguna));

        // Verifikasi database benar-benar berubah
        $updatedUser = $user->fresh();
        $this->assertTrue(Hash::check('passwordBaru123', $updatedUser->kata_sandi));
        $this->assertFalse(Hash::check('passwordLama123', $updatedUser->kata_sandi));

        // Verifikasi user bisa login dengan kata sandi baru
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'user-sandi@example.test',
            'kata_sandi' => 'passwordBaru123',
        ])->assertOk();

        $this->assertNotEmpty($loginResponse->json('token'));
    }
}
