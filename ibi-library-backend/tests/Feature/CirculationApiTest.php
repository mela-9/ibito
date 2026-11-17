<?php

namespace Tests\Feature;

use App\Models\Antrean;
use App\Models\KoleksiBuku;
use App\Models\Peminjaman;
use App\Models\User;
use App\Services\CirculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CirculationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_quota_queues_members_and_return_promotes_the_oldest_member(): void
    {
        $firstMember = $this->createMember('first@example.test');
        $nextMember = $this->createMember('next@example.test');
        $book = KoleksiBuku::query()->create([
            'judul' => 'Buku Antrean',
            'penulis' => 'Penulis',
            'kategori' => 'Fiksi',
            'kuota_maks' => 1,
        ]);

        Sanctum::actingAs($firstMember);
        $firstLoan = $this->postJson('/api/peminjaman', ['id_buku' => $book->id_buku])
            ->assertCreated()
            ->assertJsonPath('status', 'borrowed')
            ->json('data.id_peminjaman');

        Sanctum::actingAs($nextMember);
        $queueId = $this->postJson('/api/peminjaman', ['id_buku' => $book->id_buku])
            ->assertOk()
            ->assertJsonPath('status', 'queued')
            ->json('data.id_antrean');

        Sanctum::actingAs($firstMember);
        $this->postJson("/api/peminjaman/{$firstLoan}/kembali")->assertOk();

        $this->assertDatabaseHas('antrean', [
            'id_antrean' => $queueId,
            'status_antrean' => 'diproses',
        ]);
        $this->assertDatabaseHas('peminjaman', [
            'id_pengguna' => $nextMember->id_pengguna,
            'id_buku' => $book->id_buku,
            'status_pinjam' => 'aktif',
        ]);
    }

    public function test_reading_minutes_are_accumulated_and_the_reading_target_is_reported(): void
    {
        $member = $this->createMember('reader@example.test');
        $book = KoleksiBuku::query()->create([
            'judul' => 'Buku Baca',
            'penulis' => 'Penulis',
            'kategori' => 'Nonfiksi',
        ]);
        $loan = Peminjaman::query()->create([
            'id_pengguna' => $member->id_pengguna,
            'id_buku' => $book->id_buku,
            'tanggal_mulai' => now()->subDays(2),
            'tanggal_jatuh_tempo' => now()->addDays(12),
            'status_pinjam' => 'aktif',
        ]);
        Sanctum::actingAs($member);

        $this->postJson('/api/aktivitas-baca', [
            'id_peminjaman' => $loan->id_peminjaman,
            'durasi_menit' => 5,
        ])->assertOk()->assertJsonPath('total_menit_3_hari', 5)->assertJsonPath('status_syarat', false);

        $this->postJson('/api/aktivitas-baca', [
            'id_peminjaman' => $loan->id_peminjaman,
            'durasi_menit' => 5,
        ])->assertOk()->assertJsonPath('total_menit_3_hari', 10);
    }

    public function test_reading_minutes_are_counted_in_the_current_three_day_window(): void
    {
        $now = Carbon::parse('2026-11-16 12:00:00');
        Carbon::setTestNow($now);

        try {
            $member = $this->createMember('cycle-reader@example.test');
            $book = KoleksiBuku::query()->create([
                'judul' => 'Buku Siklus',
                'penulis' => 'Penulis',
                'kategori' => 'Nonfiksi',
            ]);
            $startedAt = $now->copy()->subDays(4);
            $loan = Peminjaman::query()->create([
                'id_pengguna' => $member->id_pengguna,
                'id_buku' => $book->id_buku,
                'tanggal_mulai' => $startedAt,
                'tanggal_jatuh_tempo' => $startedAt->copy()->addDays(14),
                'status_pinjam' => 'aktif',
            ]);
            $loan->aktivitasBaca()->create([
                'tanggal_baca' => $startedAt->copy()->addDays(2)->toDateString(),
                'durasi_menit' => 30,
                'status_syarat' => true,
            ]);
            Sanctum::actingAs($member);

            $this->postJson('/api/aktivitas-baca', [
                'id_peminjaman' => $loan->id_peminjaman,
                'durasi_menit' => 5,
            ])->assertOk()
                ->assertJsonPath('total_menit_3_hari', 5)
                ->assertJsonPath('status_syarat', false)
                ->assertJsonPath('target_membaca_aktif', true);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_active_loans_report_reading_window_and_due_date_urgency(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-17 12:00:00'));

        try {
            $member = $this->createMember('loan-progress@example.test');
            $book = KoleksiBuku::query()->create([
                'judul' => 'Buku Progres',
                'penulis' => 'Penulis',
                'kategori' => 'Sains',
            ]);
            $startedAt = now()->subDays(8);
            Peminjaman::query()->create([
                'id_pengguna' => $member->id_pengguna,
                'id_buku' => $book->id_buku,
                'tanggal_mulai' => $startedAt,
                'tanggal_jatuh_tempo' => now()->addDays(6),
                'status_pinjam' => 'aktif',
            ]);
            Sanctum::actingAs($member);

            $this->getJson('/api/peminjaman')
                ->assertOk()
                ->assertJsonPath('0.target_membaca_aktif', true)
                ->assertJsonPath('0.total_menit_3_hari', 0)
                ->assertJsonPath('0.hari_sampai_batas_baca', 0)
                ->assertJsonPath('0.hari_sampai_jatuh_tempo', 6);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_missed_three_day_window_times_out_and_promotes_the_next_member(): void
    {
        $now = Carbon::parse('2026-11-16 08:00:00');
        Carbon::setTestNow($now);

        try {
            $member = $this->createMember('missed-cycle@example.test');
            $nextMember = $this->createMember('next-cycle@example.test');
            $book = KoleksiBuku::query()->create([
                'judul' => 'Buku Siklus Terlewat',
                'penulis' => 'Penulis',
                'kategori' => 'Fiksi',
                'kuota_maks' => 1,
            ]);
            $startedAt = $now->copy()->subDays(9);
            $loan = Peminjaman::query()->create([
                'id_pengguna' => $member->id_pengguna,
                'id_buku' => $book->id_buku,
                'tanggal_mulai' => $startedAt,
                'tanggal_jatuh_tempo' => $startedAt->copy()->addDays(14),
                'status_pinjam' => 'aktif',
            ]);
            foreach ([2, 5] as $day) {
                $loan->aktivitasBaca()->create([
                    'tanggal_baca' => $startedAt->copy()->addDays($day)->toDateString(),
                    'durasi_menit' => 30,
                    'status_syarat' => true,
                ]);
            }
            Antrean::query()->create([
                'id_pengguna' => $nextMember->id_pengguna,
                'id_buku' => $book->id_buku,
                'nomor_urut' => 1,
                'waktu_masuk' => $now,
                'status_antrean' => 'menunggu',
            ]);

            app(CirculationService::class)->processAutomaticReturns();

            $this->assertDatabaseHas('peminjaman', [
                'id_peminjaman' => $loan->id_peminjaman,
                'status_pinjam' => 'timeout',
            ]);
            $this->assertDatabaseHas('antrean', [
                'id_pengguna' => $nextMember->id_pengguna,
                'status_antrean' => 'diproses',
            ]);
            $this->assertDatabaseHas('peminjaman', [
                'id_pengguna' => $nextMember->id_pengguna,
                'id_buku' => $book->id_buku,
                'status_pinjam' => 'aktif',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reading_reminders_are_daily_until_the_window_target_is_met(): void
    {
        $now = Carbon::parse('2026-11-16 12:00:00');
        Carbon::setTestNow($now);

        try {
            $member = $this->createMember('daily-reminder@example.test');
            $book = KoleksiBuku::query()->create([
                'judul' => 'Buku Pengingat Harian',
                'penulis' => 'Penulis',
                'kategori' => 'Nonfiksi',
            ]);
            $startedAt = $now->copy()->subDay();
            $loan = Peminjaman::query()->create([
                'id_pengguna' => $member->id_pengguna,
                'id_buku' => $book->id_buku,
                'tanggal_mulai' => $startedAt,
                'tanggal_jatuh_tempo' => $startedAt->copy()->addDays(14),
                'status_pinjam' => 'aktif',
            ]);
            $circulation = app(CirculationService::class);

            $circulation->processAutomaticReturns();
            $circulation->processAutomaticReturns();
            $this->assertSame(1, $member->notifikasi()->where('jenis_notifikasi', 'pengingat_durasi')->count());

            Carbon::setTestNow($now->copy()->addDay());
            $circulation->processAutomaticReturns();
            $this->assertSame(2, $member->notifikasi()->where('jenis_notifikasi', 'pengingat_durasi')->count());

            $loan->aktivitasBaca()->create([
                'tanggal_baca' => now()->toDateString(),
                'durasi_menit' => 30,
                'status_syarat' => true,
            ]);
            $circulation->processAutomaticReturns();
            $this->assertSame(2, $member->notifikasi()->where('jenis_notifikasi', 'pengingat_durasi')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_queue_journey_shows_names_and_positions_to_authenticated_members(): void
    {
        $viewer = $this->createMember('queue-viewer@example.test');
        $firstMember = $this->createMember('queue-first@example.test');
        $secondMember = $this->createMember('queue-second@example.test');
        $firstMember->update(['nama' => 'Anggota Pertama']);
        $secondMember->update(['nama' => 'Anggota Kedua']);
        $book = KoleksiBuku::query()->create([
            'judul' => 'Buku Transparan',
            'penulis' => 'Penulis',
            'kategori' => 'Sejarah',
        ]);
        foreach ([$firstMember, $secondMember] as $index => $member) {
            Antrean::query()->create([
                'id_pengguna' => $member->id_pengguna,
                'id_buku' => $book->id_buku,
                'nomor_urut' => $index + 1,
                'waktu_masuk' => now()->addSeconds($index),
                'status_antrean' => 'menunggu',
            ]);
        }
        Sanctum::actingAs($viewer);

        $this->getJson("/api/buku/{$book->id_buku}/antrean")
            ->assertOk()
            ->assertJsonPath('0.nama_pengguna', 'Anggota Pertama')
            ->assertJsonPath('0.posisi', 1)
            ->assertJsonPath('1.posisi', 2)
            ->assertJsonCount(2);
    }

    public function test_waiting_queue_is_limited_to_thirty_members_per_book(): void
    {
        $activeMember = $this->createMember('queue-active@example.test');
        $book = KoleksiBuku::query()->create([
            'judul' => 'Buku Antrean Penuh',
            'penulis' => 'Penulis',
            'kategori' => 'Fiksi',
            'kuota_maks' => 1,
        ]);
        Peminjaman::query()->create([
            'id_pengguna' => $activeMember->id_pengguna,
            'id_buku' => $book->id_buku,
            'tanggal_mulai' => now(),
            'tanggal_jatuh_tempo' => now()->addDays(14),
            'status_pinjam' => 'aktif',
        ]);

        for ($index = 0; $index < 30; $index++) {
            $member = $this->createMember("waiting{$index}@example.test");
            Antrean::query()->create([
                'id_pengguna' => $member->id_pengguna,
                'id_buku' => $book->id_buku,
                'nomor_urut' => $index + 1,
                'waktu_masuk' => now()->addSeconds($index),
                'status_antrean' => 'menunggu',
            ]);
        }

        $newMember = $this->createMember('waiting-new@example.test');
        Sanctum::actingAs($newMember);
        $this->postJson('/api/peminjaman', ['id_buku' => $book->id_buku])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_buku');

        $this->assertDatabaseCount('antrean', 30);
    }

    public function test_scheduler_times_out_loans_without_reading_and_promotes_the_fifo_member(): void
    {
        $now = now();
        Carbon::setTestNow($now);
        $member = $this->createMember('expired@example.test');
        $nextMember = $this->createMember('waiting@example.test');
        $book = KoleksiBuku::query()->create([
            'judul' => 'Buku Jatuh Tempo',
            'penulis' => 'Penulis',
            'kategori' => 'Fiksi',
            'kuota_maks' => 1,
        ]);
        $loan = Peminjaman::query()->create([
            'id_pengguna' => $member->id_pengguna,
            'id_buku' => $book->id_buku,
            'tanggal_mulai' => $now->copy()->subDays(4),
            'tanggal_jatuh_tempo' => $now->copy()->addDays(10),
            'status_pinjam' => 'aktif',
        ]);
        Antrean::query()->create([
            'id_pengguna' => $nextMember->id_pengguna,
            'id_buku' => $book->id_buku,
            'nomor_urut' => 1,
            'waktu_masuk' => $now,
            'status_antrean' => 'menunggu',
        ]);

        try {
            app(CirculationService::class)->processAutomaticReturns();

            $this->assertDatabaseHas('peminjaman', [
                'id_peminjaman' => $loan->id_peminjaman,
                'status_pinjam' => 'timeout',
            ]);
            $this->assertDatabaseHas('antrean', [
                'id_pengguna' => $nextMember->id_pengguna,
                'status_antrean' => 'diproses',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_notifications_include_book_id_and_book_relationship(): void
    {
        $member = $this->createMember('notif-target@example.test');
        $book = KoleksiBuku::query()->create([
            'judul' => 'Buku Notifikasi Uji',
            'penulis' => 'Penulis Notif',
            'kategori' => 'Sains',
            'kuota_maks' => 0,
        ]);

        Sanctum::actingAs($member);
        $this->postJson('/api/peminjaman', ['id_buku' => $book->id_buku])
            ->assertOk()
            ->assertJsonPath('status', 'queued');

        $this->getJson('/api/notifikasi')
            ->assertOk()
            ->assertJsonPath('0.id_buku', $book->id_buku)
            ->assertJsonPath('0.buku.id_buku', $book->id_buku)
            ->assertJsonPath('0.buku.judul', 'Buku Notifikasi Uji');
    }

    private function createMember(string $email): User
    {
        return User::query()->create([
            'nama' => 'Anggota',
            'email' => $email,
            'kata_sandi' => 'kata-sandi-uji',
            'peran' => 'anggota',
            'status_verifikasi' => true,
        ]);
    }
}
