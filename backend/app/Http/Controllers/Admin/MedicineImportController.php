<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportMedicinesJob;
use App\Models\MedicineImport;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MedicineImportController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);
        $file = $data['file'];
        $path = $file->store('medicine-imports');

        $import = MedicineImport::create([
            'user_id' => $request->user()?->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'status' => MedicineImport::STATUS_QUEUED,
        ]);

        ImportMedicinesJob::dispatch($import->id);

        $this->auditLogger->success($request, 'import_queued', 'medicine', 'Import obat masuk antrean queue.', [
            'medicine_import_id' => $import->id,
            'original_filename' => $import->original_filename,
        ], httpStatus: Response::HTTP_ACCEPTED);

        return response()->json([
            'message' => 'Import obat masuk antrean.',
            'data' => $import->fresh(),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(MedicineImport $medicineImport): JsonResponse
    {
        return response()->json([
            'data' => $medicineImport,
        ]);
    }
}
