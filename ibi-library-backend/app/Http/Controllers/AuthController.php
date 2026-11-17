<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:pengguna,email'],
            'kata_sandi' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            ...$validated,
            'peran' => 'anggota',
            'status_verifikasi' => false,
        ]);

        return response()->json([
            'message' => 'Pendaftaran berhasil. Akun menunggu verifikasi pustakawan.',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'kata_sandi' => ['required', 'string'],
        ]);
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['kata_sandi'], $user->kata_sandi)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi tidak sesuai.'],
            ]);
        }

        if (! $user->status_verifikasi) {
            return response()->json([
                'message' => 'Akun Anda menunggu verifikasi pustakawan.',
            ], 403);
        }

        return response()->json([
            'token' => $user->createToken('ibi-library-spa')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Anda berhasil keluar.']);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update([
            'nama' => $validated['nama'],
        ]);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->fresh(),
        ]);
    }

    public function requestPasswordOtp(Request $request)
    {
        $user = $request->user();
        $otp = (string) random_int(100000, 999999);

        Cache::put('password_otp_'.$user->id_pengguna, $otp, now()->addMinutes(15));

        return response()->json([
            'message' => 'Kode OTP berhasil dibuat dan dikirim ke email Anda.',
            'otp' => $otp,
            'email' => $user->email,
            'expires_in_minutes' => 15,
        ]);
    }

    public function changePasswordWithOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'string'],
            'kata_sandi' => ['required', 'string', 'min:8'],
        ]);

        $user = $request->user();
        $cachedOtp = Cache::get('password_otp_'.$user->id_pengguna);

        if (! $cachedOtp || (string) $cachedOtp !== trim($validated['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Kode OTP salah atau sudah kedaluwarsa.'],
            ]);
        }

        $user->update([
            'kata_sandi' => $validated['kata_sandi'],
        ]);

        Cache::forget('password_otp_'.$user->id_pengguna);

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui di database.',
            'user' => $user->fresh(),
        ]);
    }

    public function verifyMember(Request $request, User $pengguna)
    {
        abort_unless($request->user()->peran === 'pustakawan', 403);
        $pengguna->update(['status_verifikasi' => true]);

        return response()->json(['user' => $pengguna]);
    }

    public function pendingMembers(Request $request)
    {
        abort_unless($request->user()->peran === 'pustakawan', 403);

        return User::query()
            ->where('peran', 'anggota')
            ->where('status_verifikasi', false)
            ->orderBy('created_at')
            ->get(['id_pengguna', 'nama', 'email', 'created_at']);
    }
}
