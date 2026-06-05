<?php

namespace App\Jobs;

use App\Models\ReportJob;
use App\Services\AuditLogger;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateLargeReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $reportJobId)
    {
    }

    public function handle(ReportService $reports, AuditLogger $auditLogger): void
    {
        $reportJob = ReportJob::query()->with('user')->findOrFail($this->reportJobId);
        $reportJob->update([
            'status' => ReportJob::STATUS_RUNNING,
            'progress' => 15,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $request = $this->requestFor($reportJob);
        $auditLogger->success($request, 'report_job_start', 'report', 'Job laporan besar dimulai.', [
            'report_job_id' => $reportJob->id,
            'format' => $reportJob->format,
            'filters' => $reportJob->filters,
        ], $reportJob->user);

        try {
            $payload = $reports->exportPayload($reportJob->filters ?? []);
            [$from, $to] = $reports->period($reportJob->filters ?? []);
            $fileName = sprintf(
                'laporan-penjualan-%s-%s.%s',
                $from->format('Ymd'),
                $to->format('Ymd'),
                $reportJob->format === 'excel' ? 'xls' : 'pdf'
            );
            $filePath = 'reports/'.$fileName;

            if ($reportJob->format === 'excel') {
                $contents = $reports->exportExcelXml($payload, $from, $to);
                Storage::disk('local')->put($filePath, $contents);
            } elseif ($reportJob->format === 'pdf') {
                $contents = Pdf::loadView('reports.sales-pdf', [
                    'payload' => $payload,
                    'from' => $from,
                    'to' => $to,
                    'generatedAt' => now(),
                ])->output();
                Storage::disk('local')->put($filePath, $contents);
            } else {
                throw new RuntimeException('Format laporan tidak didukung.');
            }

            $reportJob->update([
                'status' => ReportJob::STATUS_FINISHED,
                'progress' => 100,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'finished_at' => now(),
                'error_message' => null,
            ]);

            $auditLogger->success($request, 'report_job_finish', 'report', 'Job laporan besar selesai.', [
                'report_job_id' => $reportJob->id,
                'format' => $reportJob->format,
                'file_path' => $filePath,
            ], $reportJob->user);
        } catch (Throwable $throwable) {
            $reportJob->update([
                'status' => ReportJob::STATUS_FAILED,
                'progress' => 100,
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ]);

            $auditLogger->failed($request, 'report_job_finish', 'report', 'Job laporan besar gagal.', $throwable->getMessage(), [
                'report_job_id' => $reportJob->id,
                'format' => $reportJob->format,
            ], $reportJob->user);

            throw $throwable;
        }
    }

    private function requestFor(ReportJob $reportJob): Request
    {
        $request = Request::create('/api/admin/reports/queue', 'POST');
        $request->setUserResolver(fn () => $reportJob->user);

        return $request;
    }
}
