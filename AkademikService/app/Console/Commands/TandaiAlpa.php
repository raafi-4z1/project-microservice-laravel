<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\AbsensiHarian;
use App\Models\PeriodeKhusus;
use App\Models\SemesterAktif;
use App\Models\SiswaKelas;

/**
 * Tandai siswa yang tidak punya catatan absensi harian sebagai `alpa`.
 * Dijalankan terjadwal tiap sore (setelah jam pulang).
 *
 * Dilewati kalau: akhir pekan, atau tanggal tsb masuk periode berjenis `libur`.
 * Siswa yang sudah punya catatan apa pun (hadir/terlambat/izin/sakit) tidak
 * disentuh. Idempotent — aman dijalankan berkali-kali (unique siswa_id+tanggal).
 */
class TandaiAlpa extends Command
{
    protected $signature = 'absensi:tandai-alpa
        {--tanggal= : Tanggal YYYY-MM-DD (default: hari ini WIB)}
        {--dry-run : Tampilkan saja, jangan simpan}';

    protected $description = 'Tandai siswa tanpa catatan absensi sebagai alpa (metode=turunan)';

    private const TZ = 'Asia/Jakarta';

    public function handle(): int
    {
        $tanggal = $this->option('tanggal') ?: Carbon::now(self::TZ)->toDateString();

        try {
            $date = Carbon::parse($tanggal, self::TZ);
        } catch (\Exception $e) {
            $this->error("Tanggal tidak valid: {$tanggal}");
            return self::FAILURE;
        }
        $tanggal = $date->toDateString();

        // 1. Akhir pekan -> bukan hari sekolah
        if (in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
            $this->info("{$tanggal}: akhir pekan — dilewati.");
            return self::SUCCESS;
        }

        // 2. Periode libur -> tidak ada sekolah
        $periode = PeriodeKhusus::untukTanggal($tanggal);
        if ($periode && $periode->isLibur()) {
            $this->info("{$tanggal}: libur ({$periode->nama}) — dilewati.");
            return self::SUCCESS;
        }

        $semester = SemesterAktif::where('is_aktif', true)->first();
        if (!$semester) {
            $this->error('Belum ada semester aktif yang ditetapkan.');
            return self::FAILURE;
        }

        // Siswa terdaftar di kelas pada semester aktif
        $siswaIds = SiswaKelas::where('tahun_ajaran', $semester->tahun_ajaran)
            ->where('semester', $semester->semester)
            ->pluck('siswa_id')
            ->unique();

        if ($siswaIds->isEmpty()) {
            $this->warn("{$tanggal}: tidak ada siswa terdaftar di semester aktif.");
            return self::SUCCESS;
        }

        // Yang sudah punya catatan apa pun hari itu -> jangan disentuh
        $sudahAda = AbsensiHarian::where('tanggal', $tanggal)
            ->whereIn('siswa_id', $siswaIds)
            ->pluck('siswa_id');

        $belum = $siswaIds->diff($sudahAda)->values();

        $this->info("{$tanggal} ({$periode?->nama} " . ($periode ? "[{$periode->jenis}]" : 'normal') . ")");
        $this->line("  terdaftar : {$siswaIds->count()}");
        $this->line("  sudah ada : {$sudahAda->count()}");
        $this->line("  akan alpa : {$belum->count()}");

        if ($belum->isEmpty()) {
            $this->info('Tidak ada yang perlu ditandai.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->comment('dry-run — tidak ada yang disimpan.');
            return self::SUCCESS;
        }

        $now  = now();
        $rows = $belum->map(fn($sid) => [
            'siswa_id'     => $sid,
            'tanggal'      => $tanggal,
            'tahun_ajaran' => $semester->tahun_ajaran,
            'semester'     => $semester->semester,
            'jam_masuk'    => null,
            'status'       => 'alpa',
            'metode'       => 'turunan',
            'terminal_id'  => null,
            'dicatat_oleh' => null,
            'keterangan'   => 'Ditandai otomatis (tidak ada catatan absensi)',
            'created_at'   => $now,
            'updated_at'   => $now,
        ])->all();

        // insertOrIgnore: aman kalau ada baris masuk di sela-sela (unique siswa_id+tanggal)
        foreach (array_chunk($rows, 500) as $chunk) {
            AbsensiHarian::insertOrIgnore($chunk);
        }

        $this->info("Selesai — {$belum->count()} siswa ditandai alpa.");
        return self::SUCCESS;
    }
}
