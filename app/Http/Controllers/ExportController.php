<?php

namespace App\Http\Controllers;

use App\Services\Transfer\Exporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    /** ?format=json (respaldo completo, por defecto) | csv (movimientos) */
    public function __invoke(Request $request, Exporter $exporter): Response
    {
        $user = $request->user();
        $stamp = now()->format('Y-m-d');

        if ($request->query('format') === 'csv') {
            return response($exporter->csv($user), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"fincontrol-movimientos-{$stamp}.csv\"",
            ]);
        }

        return response(json_encode($exporter->json($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"fincontrol-respaldo-{$stamp}.json\"",
        ]);
    }
}
