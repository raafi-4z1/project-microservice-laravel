<?php

namespace App\Console\Commands;

use App\Models\Terminal;
use Illuminate\Console\Command;

class RegisterTerminal extends Command
{
    protected $signature = 'terminal:register
        {--nama= : Nama terminal (mis. "Gerbang Utama")}
        {--lokasi= : gerbang|ruang_guru|tu}
        {--mode=demo : produksi|demo}
        {--ip= : (produksi) daftar IP/CIDR LAN, comma-separated}
        {--lat= : (demo) latitude titik sekolah}
        {--lng= : (demo) longitude titik sekolah}
        {--radius=150 : (demo) radius geofence dalam meter}';

    protected $description = 'Daftarkan terminal absensi dan cetak token (token hanya ditampilkan sekali).';

    public function handle(): int
    {
        $nama   = $this->option('nama')   ?: $this->ask('Nama terminal');
        $lokasi = $this->option('lokasi') ?: $this->choice('Lokasi', ['gerbang', 'ruang_guru', 'tu'], 0);
        $mode   = $this->option('mode');

        if (!in_array($lokasi, ['gerbang', 'ruang_guru', 'tu'], true)) {
            $this->error("lokasi tidak valid: {$lokasi}");
            return self::FAILURE;
        }
        if (!in_array($mode, ['produksi', 'demo'], true)) {
            $this->error("mode tidak valid: {$mode}");
            return self::FAILURE;
        }

        $data = [
            'nama'     => $nama,
            'lokasi'   => $lokasi,
            'mode'     => $mode,
            'is_aktif' => true,
        ];

        if ($mode === 'produksi') {
            $ip = $this->option('ip') ?: $this->ask('Daftar IP/CIDR LAN (comma-separated)');
            if (!$ip) {
                $this->error('Mode produksi wajib --ip (allowlist LAN).');
                return self::FAILURE;
            }
            $data['ip_allowlist'] = $ip;
        } else {
            $lat = $this->option('lat') ?: $this->ask('Latitude titik sekolah');
            $lng = $this->option('lng') ?: $this->ask('Longitude titik sekolah');
            $data['lat']      = $lat;
            $data['lng']      = $lng;
            $data['radius_m'] = (int) $this->option('radius');
        }

        [$plain, $hash] = Terminal::generateToken();
        $data['token_hash'] = $hash;

        $terminal = Terminal::create($data);

        $this->info("Terminal terdaftar (id={$terminal->id}, {$terminal->nama} @ {$terminal->lokasi}, mode={$terminal->mode}).");
        $this->newLine();
        $this->warn('Token terminal (SIMPAN — hanya ditampilkan sekali):');
        $this->line("  X-Terminal-Id    : {$terminal->id}");
        $this->line("  X-Terminal-Token : {$plain}");

        return self::SUCCESS;
    }
}
