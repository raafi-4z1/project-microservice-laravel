<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Paginasi OPSIONAL untuk endpoint riwayat lintas-semester.
 *
 * Endpoint riwayat semula selalu membalas array datar berisi SELURUH baris.
 * Aman untuk data sekarang (puluhan baris), tapi kelas lama yang melintasi
 * banyak tahun ajaran bisa tumbuh ke ratusan/ribuan baris dan seluruhnya ikut
 * terkirim dalam satu panggilan.
 *
 * Kontrak yang dipilih:
 * - TANPA `page`/`per_page`  -> array datar, persis seperti sebelumnya.
 *   Klien lama tidak perlu diubah dan tidak ada yang rusak.
 * - DENGAN `page`/`per_page` -> envelope paginasi yang SAMA dengan `GET /guru/all`
 *   (data, current_page, last_page, per_page, total, from, to, links) sehingga
 *   klien memakai satu bentuk parser untuk semua daftar.
 *
 * Bentuk tiap item TIDAK berubah dalam kedua mode — hanya pembungkusnya.
 */
trait PaginasiRiwayat
{
    /** Batas atas per_page: penjaga agar satu permintaan tak menarik semuanya sekaligus. */
    public const RIWAYAT_MAX_PER_PAGE = 100;

    /** Dipakai kalau klien hanya mengirim `page` tanpa `per_page`. */
    public const RIWAYAT_DEFAULT_PER_PAGE = 25;

    /** Aturan validasi paginasi, digabung ke aturan filter tiap endpoint. */
    protected function aturanPaginasi(): array
    {
        return [
            'page'     => 'sometimes|numeric|min:1',
            'per_page' => 'sometimes|numeric|min:1|max:' . self::RIWAYAT_MAX_PER_PAGE,
        ];
    }

    protected function paginasiDiminta(Request $request): bool
    {
        return $request->filled('page') || $request->filled('per_page');
    }

    /**
     * Jalankan query lalu balas dalam bentuk yang sesuai permintaan klien.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  callable(array): array                 $map  pemeta satu baris ke bentuk API
     * @return array  array datar, atau envelope paginasi
     */
    protected function hasilRiwayat(Request $request, Builder $query, callable $map): array
    {
        if (!$this->paginasiDiminta($request)) {
            return $query->get()->map(fn($r) => $map($r->toArray()))->all();
        }

        $perPage = (int) $request->input('per_page', self::RIWAYAT_DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::RIWAYAT_MAX_PER_PAGE));

        $paginator = $query->paginate($perPage)->withQueryString();

        $current = $paginator->currentPage();
        $last    = $paginator->lastPage();
        $start   = max(1, $current - 2);
        $end     = min($last, $current + 2);

        $links = collect($paginator->getUrlRange($start, $end))
            ->map(fn($url, $page) => [
                'query'  => parse_url($url, PHP_URL_QUERY),
                'label'  => (string) $page,
                'page'   => (int) $page,
                'active' => $page == $current,
            ])
            ->values()
            ->all();

        if ($paginator->onFirstPage() === false) {
            array_unshift($links, [
                'query'  => parse_url($paginator->previousPageUrl(), PHP_URL_QUERY),
                'label'  => '&laquo; Previous',
                'page'   => $current - 1,
                'active' => false,
            ]);
        }

        if ($paginator->hasMorePages()) {
            $links[] = [
                'query'  => parse_url($paginator->nextPageUrl(), PHP_URL_QUERY),
                'label'  => 'Next &raquo;',
                'page'   => $current + 1,
                'active' => false,
            ];
        }

        $pageArr          = $paginator->toArray();
        $pageArr['data']  = collect($pageArr['data'])->map(fn($item) => $map((array) $item))->all();
        $pageArr['links'] = $links;

        // URL absolut menunjuk ke host service internal, bukan Gateway — kalau
        // ikut terkirim, klien akan mencoba memanggil alamat yang tak terjangkau.
        unset(
            $pageArr['first_page_url'],
            $pageArr['last_page_url'],
            $pageArr['next_page_url'],
            $pageArr['prev_page_url'],
            $pageArr['path']
        );

        return $pageArr;
    }
}
