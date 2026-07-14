<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KartuController extends Controller
{
    use ApiResponser;

    /**
     * Render QR dari kartu_uid sebagai SVG (untuk dicetak di kartu).
     * SVG dipilih agar tidak butuh ekstensi imagick (PNG simple-qrcode butuh imagick).
     * GET /api/kartu/qr?data=SIS-XXXXXXXX
     */
    public function qr(Request $request)
    {
        $data = trim((string) $request->query('data'));

        if ($data === '' || strlen($data) > 64) {
            return $this->response('Parameter "data" (kartu_uid) wajib, maks 64 karakter.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $facade = \SimpleSoftwareIO\QrCode\Facades\QrCode::class;
        if (!class_exists($facade)) {
            return $this->response(
                'Package QR belum terpasang. Jalankan di Gateway: composer require simplesoftwareio/simple-qrcode',
                Response::HTTP_NOT_IMPLEMENTED
            );
        }

        $svg = $facade::format('svg')->size(320)->margin(1)->errorCorrection('M')->generate($data);

        return response($svg, Response::HTTP_OK)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'no-store');
    }
}
