<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleExternalProcessorsRequest;
use App\Http\Requests\v2\IndexExternalProcessorRequest;
use App\Http\Requests\v2\StoreExternalProcessorRequest;
use App\Http\Requests\v2\UpdateExternalProcessorRequest;
use App\Http\Resources\v2\ExternalProcessorResource;
use App\Models\ExternalProcessor;
use Illuminate\Http\JsonResponse;

class ExternalProcessorController extends Controller
{
    public function index(IndexExternalProcessorRequest $request)
    {
        $query = ExternalProcessor::query()
            ->with('country')
            ->orderBy('name');

        if ($request->filled('id')) {
            $query->where('id', $request->integer('id'));
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        if ($request->filled('name')) {
            $name = $request->input('name');
            $query->where(function ($subQuery) use ($name) {
                $subQuery->where('name', 'like', '%'.$name.'%')
                    ->orWhere('legal_name', 'like', '%'.$name.'%');
            });
        }

        if ($request->filled('vatNumber')) {
            $query->where('vat_number', 'like', '%'.$request->input('vatNumber').'%');
        }

        if ($request->filled('sanitaryRegistrationNumber')) {
            $query->where('sanitary_registration_number', 'like', '%'.$request->input('sanitaryRegistrationNumber').'%');
        }

        if ($request->has('isActive')) {
            $query->where('is_active', $request->boolean('isActive'));
        }

        if ($request->filled('countryId')) {
            $query->where('country_id', $request->integer('countryId'));
        }

        return ExternalProcessorResource::collection($query->paginate($request->integer('perPage', 12)));
    }

    public function store(StoreExternalProcessorRequest $request): JsonResponse
    {
        $validated = $this->prepareForStorage($request->validated());
        $validated['is_active'] = $validated['is_active'] ?? true;

        $externalProcessor = ExternalProcessor::create($validated);

        return response()->json([
            'message' => 'Transformador externo creado correctamente.',
            'data' => new ExternalProcessorResource($externalProcessor->load('country')),
        ], 201);
    }

    public function show(ExternalProcessor $externalProcessor): JsonResponse
    {
        $this->authorize('view', $externalProcessor);

        return response()->json([
            'message' => 'Transformador externo obtenido correctamente.',
            'data' => new ExternalProcessorResource($externalProcessor->load('country')),
        ]);
    }

    public function update(UpdateExternalProcessorRequest $request, ExternalProcessor $externalProcessor): JsonResponse
    {
        $validated = $this->prepareForStorage($request->validated());
        $externalProcessor->update($validated);

        return response()->json([
            'message' => 'Transformador externo actualizado correctamente.',
            'data' => new ExternalProcessorResource($externalProcessor->fresh()->load('country')),
        ]);
    }

    public function destroy(ExternalProcessor $externalProcessor): JsonResponse
    {
        $this->authorize('delete', $externalProcessor);

        $externalProcessor->delete();

        return response()->json(['message' => 'Transformador externo eliminado correctamente.']);
    }

    public function destroyMultiple(DestroyMultipleExternalProcessorsRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');
        $externalProcessors = ExternalProcessor::whereIn('id', $ids)->get();

        foreach ($externalProcessors as $externalProcessor) {
            $this->authorize('delete', $externalProcessor);
        }

        ExternalProcessor::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Transformadores externos eliminados correctamente.']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', ExternalProcessor::class);

        $externalProcessors = ExternalProcessor::query()
            ->select('id', 'name', 'vat_number', 'is_active')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ExternalProcessor $externalProcessor) => [
                'id' => $externalProcessor->id,
                'name' => $externalProcessor->name,
                'vatNumber' => $externalProcessor->vat_number,
                'isActive' => $externalProcessor->is_active,
            ]);

        return response()->json($externalProcessors);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function prepareForStorage(array $validated): array
    {
        $fieldMap = [
            'name' => 'name',
            'legalName' => 'legal_name',
            'vatNumber' => 'vat_number',
            'sanitaryRegistrationNumber' => 'sanitary_registration_number',
            'contactPerson' => 'contact_person',
            'phone' => 'phone',
            'address' => 'address',
            'city' => 'city',
            'postalCode' => 'postal_code',
            'province' => 'province',
            'countryId' => 'country_id',
            'isActive' => 'is_active',
            'notes' => 'notes',
        ];

        $mapped = [];

        foreach ($fieldMap as $inputKey => $column) {
            if (array_key_exists($inputKey, $validated)) {
                $mapped[$column] = $validated[$inputKey];
            }
        }

        if (array_key_exists('emails', $validated) || array_key_exists('ccEmails', $validated)) {
            $mapped['emails'] = $this->prepareEmailsForStorage($validated);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function prepareEmailsForStorage(array $validated): ?string
    {
        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim((string) $email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:'.trim((string) $email);
        }

        return count($allEmails) > 0
            ? implode(";\n", $allEmails).';'
            : null;
    }
}
