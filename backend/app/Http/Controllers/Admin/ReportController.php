<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\GenerateLargeReportJob;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReportJob;
use App\Services\AuditLogger;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function sales(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->sales($this->filters($request)),
        ]);
    }

    public function topMedicines(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->topMedicines($this->filters($request)),
        ]);
    }

    public function expiringMedicines(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->expiringMedicines($this->filters($request)),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->transactions($this->filters($request)),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->filters($request);
        $payload = $this->reports->exportPayload($filters);
        [$from, $to] = $this->reports->period($filters);

        $this->auditLogger->success($request, 'export_pdf', 'report', 'Export laporan penjualan PDF.', [
            'filters' => $filters,
        ]);

        return Pdf::loadView('reports.sales-pdf', [
            'payload' => $payload,
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
        ])->download('laporan-penjualan-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf');
    }

    public function exportExcel(Request $request)
    {
        $filters = $this->filters($request);
        $payload = $this->reports->exportPayload($filters);
        [$from, $to] = $this->reports->period($filters);
        $fileName = 'laporan-penjualan-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xls';
        $xml = $this->reports->exportExcelXml($payload, $from, $to);

        $this->auditLogger->success($request, 'export_excel', 'report', 'Export laporan penjualan Excel.', [
            'filters' => $filters,
        ]);

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function queue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'order_status' => ['nullable', Rule::in(Order::STATUSES)],
            'format' => ['required', Rule::in(['pdf', 'excel'])],
        ]);

        $reportJob = ReportJob::create([
            'user_id' => $request->user()->id,
            'format' => $validated['format'],
            'status' => ReportJob::STATUS_PENDING,
            'progress' => 0,
            'filters' => collect($validated)->except('format')->all(),
        ]);

        GenerateLargeReportJob::dispatch($reportJob->id);

        $this->auditLogger->success($request, 'report_queue', 'report', 'Job laporan besar masuk antrean.', [
            'report_job_id' => $reportJob->id,
            'format' => $reportJob->format,
            'filters' => $reportJob->filters,
        ]);

        return response()->json([
            'message' => 'Job laporan berhasil diantrikan.',
            'data' => $this->serializeQueueJob($reportJob->fresh('user')),
        ], 202);
    }

    public function queueIndex(Request $request): JsonResponse
    {
        $jobs = ReportJob::query()
            ->with('user:id,name,email,role')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (ReportJob $job): array => $this->serializeQueueJob($job))
            ->all();

        return response()->json(['data' => $jobs]);
    }

    public function queueShow(ReportJob $reportJob): JsonResponse
    {
        $reportJob->load('user:id,name,email,role');

        return response()->json([
            'data' => $this->serializeQueueJob($reportJob),
        ]);
    }

    public function queueDownload(ReportJob $reportJob)
    {
        if ($reportJob->status !== ReportJob::STATUS_FINISHED || ! $reportJob->file_path || ! Storage::disk('local')->exists($reportJob->file_path)) {
            return response()->json([
                'message' => 'File laporan belum tersedia.',
            ], 409);
        }

        $extension = $reportJob->format === 'excel' ? 'xls' : 'pdf';
        $name = $reportJob->file_name ?: 'laporan-penjualan.'.$extension;

        return Storage::disk('local')->download($reportJob->file_path, $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'order_status' => ['nullable', Rule::in(Order::STATUSES)],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeQueueJob(ReportJob $job): array
    {
        return [
            'id' => $job->id,
            'format' => $job->format,
            'status' => $job->status,
            'progress' => (int) $job->progress,
            'filters' => $job->filters ?? [],
            'file_name' => $job->file_name,
            'file_path' => $job->file_path,
            'download_url' => $job->download_url,
            'error_message' => $job->error_message,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
            'created_at' => $job->created_at,
            'user' => $job->user ? [
                'id' => $job->user->id,
                'name' => $job->user->name,
                'email' => $job->user->email,
                'role' => $job->user->role,
            ] : null,
        ];
    }
}
