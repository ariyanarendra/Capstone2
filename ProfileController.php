<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->payload($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Data identitas dosen dan mahasiswa hanya dapat diubah oleh Administrator.',
            ], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'ends_with:@gmail.com', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'email.ends_with' => 'Email harus menggunakan alamat @gmail.com.',
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $this->payload($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                Password::min(8)->letters()->numbers(),
                'confirmed',
                'different:current_password',
            ],
        ], [
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi sementara atau kata sandi saat ini.',
            'password.letters' => 'Kata sandi baru harus memiliki setidaknya satu huruf.',
            'password.numbers' => 'Kata sandi baru harus memiliki setidaknya satu angka.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak sama.',
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Kata sandi saat ini tidak sesuai.'], 422);
        }

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui. Anda sekarang dapat menggunakan seluruh layanan GateKampus.',
            'user' => $this->payload($user->fresh()),
        ]);
    }

    private function payload(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'email',
            'identifier',
            'role',
            'program_studi',
            'is_active',
            'must_change_password',
            'created_at',
        ]);
    }
}
