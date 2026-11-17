<?php

namespace Tests\Feature;

use App\Models\KoleksiBuku;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_demo_member_has_all_scenarios_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 15:40:00'));

        try {
            app(DemoDataSeeder::class)->run();
            app(DemoDataSeeder::class)->run();

            $member = User::query()->where('email', 'anggota101@demo.ibi.local')->firstOrFail();

            $this->assertSame('anggota', $member->peran);
            $this->assertTrue($member->status_verifikasi);
            $this->assertSame(5, $member->peminjaman()->count());
            $this->assertSame(4, $member->peminjaman()->where('status_pinjam', 'aktif')->count());
            $this->assertSame(1, $member->peminjaman()->where('status_pinjam', 'selesai')->count());
            $this->assertSame(6, $member->antrean()->where('status_antrean', 'menunggu')->count());
            $this->assertSame(1, $member->antrean()->where('status_antrean', 'batal')->count());
            $this->assertSame(9, $member->notifikasi()->where('status_baca', false)->count());

            $expectedLoans = [
                'Catatan tentang Hidup Berkelanjutan' => '2026-11-19',
                'Catatan tentang Ekonomi Kreatif' => '2026-11-20',
                'Menjelajahi Hutan Tropis' => '2026-11-21',
                'Belajar dari Bahasa Nusantara' => '2026-12-01',
            ];
            $actualLoans = $member->peminjaman()
                ->where('status_pinjam', 'aktif')
                ->with('buku')
                ->get()
                ->mapWithKeys(fn ($loan) => [$loan->buku->judul => $loan->tanggal_jatuh_tempo->toDateString()])
                ->all();
            $this->assertSame($expectedLoans, $actualLoans);

            $creativeBook = KoleksiBuku::query()
                ->where('judul', 'Catatan tentang Ekonomi Kreatif')
                ->firstOrFail();
            $creativeLoanId = $member->peminjaman()
                ->where('id_buku', $creativeBook->id_buku)
                ->value('id_peminjaman');
            $this->assertSame(4, DB::table('aktivitas_baca')->where('id_peminjaman', $creativeLoanId)->count());

            $tropicalBook = KoleksiBuku::query()
                ->where('judul', 'Menjelajahi Hutan Tropis')
                ->firstOrFail();
            $tropicalLoanId = $member->peminjaman()
                ->where('id_buku', $tropicalBook->id_buku)
                ->value('id_peminjaman');
            $this->assertSame(4, DB::table('aktivitas_baca')->where('id_peminjaman', $tropicalLoanId)->count());

            $queueBook = KoleksiBuku::query()
                ->where('judul', 'Catatan tentang Seni Membatik')
                ->firstOrFail();
            $cancelledQueue = $member->antrean()->where('id_buku', $queueBook->id_buku)->firstOrFail();
            $this->assertSame('batal', $cancelledQueue->status_antrean);
        } finally {
            Carbon::setTestNow();
        }
    }
}
