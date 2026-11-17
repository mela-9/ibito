<?php

namespace App\Http\Controllers;

use App\Models\KoleksiBuku;
use Illuminate\Http\Request;

class KoleksiBukuController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:255'],
            'tahun_terbit' => ['nullable', 'integer', 'between:1000,2100'],
            'penulis' => ['nullable', 'string', 'max:255'],
        ]);

        return KoleksiBuku::query()
            ->when($filters['q'] ?? null, function ($query, $value) {
                $term = '%'.trim($value).'%';
                $query->where(function ($search) use ($term) {
                    $search->where('judul', 'like', $term)
                        ->orWhere('penulis', 'like', $term)
                        ->orWhere('kategori', 'like', $term)
                        ->orWhere('kata_kunci', 'like', $term);
                });
            })
            ->when($filters['genre'] ?? null, fn ($query, $genre) => $query->where('kategori', $genre))
            ->when($filters['tahun_terbit'] ?? null, fn ($query, $year) => $query->where('tahun_terbit', $year))
            ->when($filters['penulis'] ?? null, fn ($query, $author) => $query->where('penulis', 'like', '%'.trim($author).'%'))
            ->withCount([
                'peminjamanAktif as peminjam_aktif',
                'antrean as antrean_menunggu',
            ])
            ->orderBy('judul')
            ->get()
            ->map(function (KoleksiBuku $book) {
                $book->sisa_kuota = max(0, $book->kuota_maks - $book->peminjam_aktif);

                return $book;
            });
    }

    public function show(KoleksiBuku $koleksiBuku)
    {
        $koleksiBuku->loadCount([
            'peminjamanAktif as peminjam_aktif',
            'antrean as antrean_menunggu',
        ]);
        $koleksiBuku->sisa_kuota = max(0, $koleksiBuku->kuota_maks - $koleksiBuku->peminjam_aktif);

        return $koleksiBuku;
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->peran === 'pustakawan', 403);
        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'penulis' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'string', 'max:255'],
            'tahun_terbit' => ['nullable', 'integer', 'between:1000,2100'],
            'kata_kunci' => ['nullable', 'array', 'max:10'],
            'kata_kunci.*' => ['string', 'max:80'],
            'ringkasan' => ['nullable', 'string'],
            'berkas_ebook' => ['nullable', 'string', 'max:2048'],
            'kuota_maks' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        return response()->json(['data' => KoleksiBuku::query()->create($validated)], 201);
    }

    public function update(Request $request, KoleksiBuku $koleksiBuku)
    {
        abort_unless($request->user()->peran === 'pustakawan', 403);
        $validated = $request->validate([
            'judul' => ['sometimes', 'required', 'string', 'max:255'],
            'penulis' => ['sometimes', 'required', 'string', 'max:255'],
            'kategori' => ['sometimes', 'required', 'string', 'max:255'],
            'tahun_terbit' => ['nullable', 'integer', 'between:1000,2100'],
            'kata_kunci' => ['nullable', 'array', 'max:10'],
            'kata_kunci.*' => ['string', 'max:80'],
            'ringkasan' => ['nullable', 'string'],
            'berkas_ebook' => ['nullable', 'string', 'max:2048'],
            'kuota_maks' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);
        $koleksiBuku->update($validated);

        return response()->json(['data' => $koleksiBuku->refresh()]);
    }

    public function destroy(Request $request, KoleksiBuku $koleksiBuku)
    {
        abort_unless($request->user()->peran === 'pustakawan', 403);
        abort_if($koleksiBuku->peminjamanAktif()->exists(), 422, 'Buku sedang dipinjam dan tidak dapat dihapus.');
        $koleksiBuku->delete();

        return response()->noContent();
    }
}
