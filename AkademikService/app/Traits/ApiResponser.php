<?php

namespace App\Traits;

use Illuminate\Http\Response;

trait ApiResponser
{
    public function response($msg, $code = Response::HTTP_OK, $data = [])
    {
        $resPhrase = "";
        $resStatus = "";
        switch ($code) {
            case Response::HTTP_OK:
                $resPhrase = "Ok"; $resStatus = "success"; break;
            case Response::HTTP_CREATED:
                $resPhrase = "Created"; $resStatus = "success"; break;
            case Response::HTTP_ACCEPTED:
                $resPhrase = "Accepted"; $resStatus = "success"; break;
            case Response::HTTP_NO_CONTENT:
                $resPhrase = "No Content"; $resStatus = "success"; break;
            case Response::HTTP_BAD_REQUEST:
                $resPhrase = "Bad Request"; $resStatus = "fail"; break;
            case Response::HTTP_UNPROCESSABLE_ENTITY:
                $resPhrase = "Unprocessible Entity"; $resStatus = "fail"; break;
            case Response::HTTP_UNAUTHORIZED:
                $resPhrase = "Unauthorized"; $resStatus = "fail"; break;
            case Response::HTTP_FORBIDDEN:
                $resPhrase = "Forbidden"; $resStatus = "fail"; break;
            case Response::HTTP_NOT_FOUND:
                $resPhrase = "Not Found"; $resStatus = "fail"; break;
            case Response::HTTP_CONFLICT:
                $resPhrase = "Conflict"; $resStatus = "fail"; break;
            case Response::HTTP_INTERNAL_SERVER_ERROR:
                $resPhrase = "Internal Server Error"; $resStatus = "fail"; break;
            default:
                break;
        }
        return response()->json([
            "resCode"   => $code,
            "resPhrase" => $resPhrase,
            "resStatus" => $resStatus,
            "resMsg"    => $msg,
            "data"      => $data,
        ], $code);
    }
}
