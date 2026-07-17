<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Terminal extends Model
{
    protected $fillable = [
        'nama',
        'lokasi',
        'token_hash',
        'mode',
        'ip_allowlist',
        'lat',
        'lng',
        'radius_m',
        'is_aktif',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'radius_m' => 'integer',
        ];
    }

    /**
     * Buat token acak untuk terminal. Kembalikan token mentah (untuk dikirim
     * sekali ke terminal); yang disimpan hanya SHA-256-nya.
     */
    public static function generateToken(): array
    {
        $plain = 'term_' . Str::random(40);
        return [$plain, hash('sha256', $plain)];
    }

    public function verifyToken(?string $plain): bool
    {
        if (!$plain) {
            return false;
        }
        return hash_equals($this->token_hash, hash('sha256', $plain));
    }

    /**
     * Cek titik (lat,lng) berada dalam radius_m dari titik terminal (mode demo).
     */
    public function withinGeofence(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null || $this->lat === null || $this->lng === null || !$this->radius_m) {
            return false;
        }

        $earth = 6371000; // meter
        $dLat = deg2rad($lat - (float) $this->lat);
        $dLng = deg2rad($lng - (float) $this->lng);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad((float) $this->lat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;
        $distance = $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $distance <= $this->radius_m;
    }

    /**
     * Cek IP request cocok dengan ip_allowlist (comma-separated IP / CIDR).
     */
    public function ipAllowed(?string $ip): bool
    {
        if (!$ip || !$this->ip_allowlist) {
            return false;
        }

        foreach (array_filter(array_map('trim', explode(',', $this->ip_allowlist))) as $entry) {
            if (str_contains($entry, '/')) {
                if ($this->ipInCidr($ip, $entry)) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
