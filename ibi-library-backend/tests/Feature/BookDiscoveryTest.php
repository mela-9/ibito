<?php

namespace Tests\Feature;

use App\Models\KoleksiBuku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_filters_by_genre_publication_year_and_author_and_searches_keywords(): void
    {
        KoleksiBuku::query()->create([
            'judul' => 'Jejak Pesisir',
            'penulis' => 'Nadia Prameswari',
            'kategori' => 'Sejarah',
            'tahun_terbit' => 2020,
            'kata_kunci' => ['jalur rempah', 'pelayaran nusantara'],
        ]);
        KoleksiBuku::query()->create([
            'judul' => 'Kebun Kota',
            'penulis' => 'Bima Adinata',
            'kategori' => 'Alam',
            'tahun_terbit' => 2022,
            'kata_kunci' => ['urban farming'],
        ]);

        $this->getJson('/api/buku?genre=Sejarah&tahun_terbit=2020&penulis=nadia')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.tahun_terbit', 2020)
            ->assertJsonPath('0.kata_kunci.0', 'jalur rempah');

        $this->getJson('/api/buku?q=pelayaran%20nusantara')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.judul', 'Jejak Pesisir');
    }
}
