<?php

namespace App\Traits;

use Illuminate\Http\Response;

/**
 * Sisipkan NAMA entitas ke respons akademik yang hanya memuat id relasi.
 *
 * Masalah yang diselesaikan: endpoint akademik sengaja hanya menyimpan/mengembalikan
 * `siswaId`/`guruId`/`mapelId`/`kelasId` — service-nya memang tidak tahu nama, itu
 * milik service lain. Klien lalu meresolusinya dari cache roster AKTIF. Untuk data
 * BERJALAN itu cukup; untuk **riwayat** tidak, karena entitas yang sudah lulus/
 * pindah/dihapus tidak ada di roster aktif dan tampil sebagai "#<id>".
 *
 * Karena itu nama diambil dari endpoint `/nama` tiap service, yang sengaja memakai
 * `withTrashed()`. Satu panggilan per JENIS entitas untuk seluruh halaman — bukan
 * satu panggilan per baris — jadi biayanya tetap konstan berapa pun jumlah barisnya.
 *
 * Kegagalan mengambil nama TIDAK menggagalkan respons: baris tetap dikembalikan
 * apa adanya (tanpa field nama). Riwayat yang tampil dengan "#id" jauh lebih baik
 * daripada layar yang gagal memuat karena satu service sedang bermasalah.
 */
trait SertakanNamaRelasi
{
    /**
     * Peta field-id -> [properti service, key id di respons /nama, key nama di respons /nama].
     * Properti service dibaca dari controller pemakai (mis. $this->siswaBaseUri).
     */
    private const SUMBER_NAMA = [
        'siswaId'    => ['siswa',    'idSiswa',     'namaLengkap'],
        'guruId'     => ['guru',     'idGuru',      'namaLengkap'],
        'karyawanId' => ['karyawan', 'idKaryawan',  'namaLengkap'],
        'mapelId'    => ['mapel',    'idPelajaran', 'namaPelajaran'],
        'kelasId'    => ['class',    'idKelas',     'namaKelas'],
    ];

    /**
     * @param  \Illuminate\Http\Response $response  hasil performRequest()
     * @param  array<string,string>      $peta      ['siswaId' => 'namaLengkap', 'kelasId' => 'namaKelas']
     *                                              field-id sumber => nama field keluaran
     */
    protected function sertakanNamaRelasi($response, array $peta)
    {
        $decode = $this->decode($response);
        if (($decode['resCode'] ?? null) !== Response::HTTP_OK) {
            return $response;
        }

        // Dua bentuk yang harus didukung: array datar (perilaku lama endpoint
        // riwayat) dan envelope paginasi (saat klien mengirim page/per_page).
        $berpaginasi = is_array($decode['data']['data'] ?? null);
        $rows = $berpaginasi ? $decode['data']['data'] : ($decode['data'] ?? null);

        if (!is_array($rows) || empty($rows)) {
            return $response;
        }

        foreach ($peta as $fieldId => $fieldNama) {
            if (!isset(self::SUMBER_NAMA[$fieldId])) {
                continue;
            }

            $ids = [];
            foreach ($rows as $r) {
                if (is_array($r) && !empty($r[$fieldId])) {
                    $ids[(int) $r[$fieldId]] = true;
                }
            }
            if (empty($ids)) {
                continue;
            }

            $kamus = $this->ambilKamusNama($fieldId, array_keys($ids));
            if ($kamus === null) {
                continue;   // service bermasalah — biarkan baris apa adanya
            }

            foreach ($rows as $i => $r) {
                if (!is_array($r) || empty($r[$fieldId])) {
                    continue;
                }
                $rows[$i][$fieldNama] = $kamus[(int) $r[$fieldId]] ?? null;
            }
        }

        if ($berpaginasi) {
            $decode['data']['data'] = $rows;
        } else {
            $decode['data'] = $rows;
        }

        return $this->response($decode['resMsg'] ?? 'OK', Response::HTTP_OK, $decode['data']);
    }

    /** @return array<int,string>|null  id => nama, atau null bila gagal */
    private function ambilKamusNama(string $fieldId, array $ids): ?array
    {
        [$svc, $keyId, $keyNama] = self::SUMBER_NAMA[$fieldId];

        $baseUri = $this->{$svc . 'BaseUri'} ?? null;
        $secret  = $this->{$svc . 'Secret'}  ?? null;
        $reqUrl  = $this->{$svc . 'ReqUrl'}  ?? null;
        if (!$baseUri || !$secret || !$reqUrl) {
            return null;
        }

        try {
            $resp = $this->decode(
                $this->callService($baseUri, $secret, 'GET', "{$reqUrl}/nama", ['ids' => implode(',', $ids)])
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (($resp['resCode'] ?? null) !== Response::HTTP_OK || !is_array($resp['data'] ?? null)) {
            return null;
        }

        $kamus = [];
        foreach ($resp['data'] as $row) {
            if (isset($row[$keyId])) {
                $kamus[(int) $row[$keyId]] = $row[$keyNama] ?? null;
            }
        }

        return $kamus;
    }
}
