<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PinWindow extends Model
{
    protected $fillable = [
        'subjek_tipe',
        'subjek_id',
        'dibuka_oleh',
        'dibuka_at',
        'berlaku_sampai',
        'terpakai_at',
    ];

    protected function casts(): array
    {
        return [
            'dibuka_at'      => 'datetime',
            'berlaku_sampai' => 'datetime',
            'terpakai_at'    => 'datetime',
        ];
    }

    /**
     * Jendela yang masih aktif: belum lewat waktu & belum dipakai.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNull('terpakai_at')
                     ->where('berlaku_sampai', '>=', now());
    }

    public function scopeUntuk(Builder $query, string $subjekTipe, int $subjekId): Builder
    {
        return $query->where('subjek_tipe', $subjekTipe)
                     ->where('subjek_id', $subjekId);
    }

    public function isAktif(): bool
    {
        return $this->terpakai_at === null && $this->berlaku_sampai >= now();
    }
}
