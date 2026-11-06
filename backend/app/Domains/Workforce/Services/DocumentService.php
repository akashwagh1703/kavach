<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Services;

use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\EmployeeDocument;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    /** @var array<string, string> */
    private const SIGNATURES = [
        'application/pdf' => '%PDF',
        'image/jpeg' => "\xFF\xD8\xFF",
        'image/png' => "\x89PNG",
        'image/webp' => 'RIFF',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Employee $employee, UploadedFile $file, string $type, string $title, ?string $expiresOn): EmployeeDocument
    {
        $mime = (string) $file->getMimeType();
        $this->assertSafe($file, $mime);
        $name = Str::uuid()->toString().'.'.$this->extension($mime);
        $path = $employee->tenant_id.'/employees/'.$employee->id.'/'.$name;
        Storage::disk('local')->put($path, fopen($file->getRealPath(), 'r'));

        return EmployeeDocument::query()->create([
            'employee_id' => $employee->id,
            'document_type' => $type,
            'title' => $title,
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
            'mime' => $mime,
            'size' => $file->getSize(),
            'expires_on' => $expiresOn,
        ]);
    }

    public function download(EmployeeDocument $document): StreamedResponse
    {
        if (! Storage::disk($document->disk)->exists($document->path)) {
            throw ValidationException::withMessages(['file' => __('documents.missing')]);
        }
        $this->audit->log('document.downloaded', $document, ['employee_id' => $document->employee_id]);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime,
        ]);
    }

    private function assertSafe(UploadedFile $file, string $mime): void
    {
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => __('documents.too_large')]);
        }
        $signature = self::SIGNATURES[$mime] ?? null;
        $handle = fopen($file->getRealPath(), 'rb');
        $bytes = $handle ? fread($handle, 12) : false;
        if ($handle) {
            fclose($handle);
        }
        if (! is_string($bytes) || $signature === null || ! str_starts_with($bytes, $signature)) {
            throw ValidationException::withMessages(['file' => __('documents.type_rejected')]);
        }
        if (preg_match('/\.(php|phtml|exe|js|html|svg)$/i', $file->getClientOriginalName())) {
            throw ValidationException::withMessages(['file' => __('documents.type_rejected')]);
        }
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
