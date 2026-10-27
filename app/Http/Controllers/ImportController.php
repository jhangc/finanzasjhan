<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRequest;
use App\Services\Transfer\Importer;
use Illuminate\Http\RedirectResponse;

class ImportController extends Controller
{
    public function __invoke(ImportRequest $request, Importer $importer): RedirectResponse
    {
        $file = $request->file('file');
        $contents = $file->get();
        $isJson = str_starts_with(ltrim($contents), '{');

        $counts = $isJson
            ? $importer->json($request->user(), $contents)
            : $importer->csv($request->user(), $contents);

        $labels = [
            'commitments' => 'gastos fijos', 'accounts' => 'cuentas', 'periods' => 'meses', 'envelopes' => 'categorías', 'movements' => 'gastos',
            'incomes' => 'ingresos', 'payments' => 'pagos', 'rules' => 'reglas', 'skipped' => 'repetidos omitidos',
        ];

        $summary = collect($counts)->filter()->map(fn ($n, $key) => "{$n} {$labels[$key]}")->join(', ');

        return back()->with('status', 'Importación lista: '.($summary ?: 'no había nada nuevo').'.');
    }
}
