<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Documents\UploadDocumentRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Models\Document;
use App\Models\Employee;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/PRD.md §82/§91 — employee documents: list, upload, private
 * download, delete. Files live in Laravel private local storage and are
 * only ever served through this authorised stream, never a public URL.
 */
class DocumentController extends Controller
{
    public function index(Employee $employee): JsonResponse
    {
        abort_unless(Gate::allows('viewForEmployee', [Document::class, $employee]), 404);

        $documents = $employee->documents()->latest('id')->get();

        return ApiResponse::data(DocumentResource::collection($documents));
    }

    public function store(UploadDocumentRequest $request, Employee $employee): JsonResponse
    {
        abort_unless(Gate::allows('manageForEmployee', [Document::class, $employee]), 403);

        $file = $request->file('file');
        $path = $file->storeAs(
            "employee-documents/{$employee->id}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local',
        );

        $document = $employee->documents()->create([
            'title' => $request->validated('title'),
            'category' => DocumentCategory::from($request->validated('category')),
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        return ApiResponse::data(new DocumentResource($document), status: 201);
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless(Gate::allows('view', $document), 404);

        app(AuditLogger::class)->record(AuditAction::DocumentDownloaded, $document);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }

    public function destroy(Document $document): Response
    {
        abort_unless(Gate::allows('delete', $document), 403);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->noContent();
    }
}
