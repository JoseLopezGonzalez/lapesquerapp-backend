<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\TransportResource;
use App\Models\Transport;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Transport::query();

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        /* name like */
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        /* adrdess like*/
        if ($request->has('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        /* Order by name*/
        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return TransportResource::collection($query->paginate($perPage));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3',
            'vatNumber' => 'required|string|regex:/^[A-Z0-9]{8,12}$/',
            'address' => 'required|string|min:10',
            'emails' => 'nullable|array',
            'emails.*' => 'email',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'email',
        ]);

        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }

        // Unir con salto de línea y añadir ; al final si hay emails
        $emailsText = count($allEmails) > 0
            ? implode(";\n", $allEmails) . ';'
            : null;

        $transport = Transport::create([
            'name' => $validated['name'],
            'vat_number' => $validated['vatNumber'],
            'address' => $validated['address'],
            'emails' => $emailsText,
        ]);

        return new TransportResource($transport);
    }




    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transport = Transport::findOrFail($id);

        return response()->json([
            'message' => 'Transportista obtenido con éxito',
            'data' => new TransportResource($transport),
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $transport = Transport::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:3',
            'vatNumber' => 'required|string|regex:/^[A-Z0-9]{8,12}$/',
            'address' => 'required|string|min:10',
            'emails' => 'nullable|array',
            'emails.*' => 'email',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'email',
        ]);

        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }

        $emailsText = count($allEmails) > 0
            ? implode(";\n", $allEmails) . ';'
            : null;

        $transport->update([
            'name' => $validated['name'],
            'vat_number' => $validated['vatNumber'],
            'address' => $validated['address'],
            'emails' => $emailsText,
        ]);

        return response()->json([
            'message' => 'Transportista actualizado con éxito',
            'data' => new TransportResource($transport),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $transport = Transport::findOrFail($id);
        $transport->delete();

        return response()->json(['message' => 'Transporte eliminado con éxito.']);
    }

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tenant.transports,id',
        ]);

        Transport::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Transportes eliminados con éxito.']);
    }

    /**
     * Get all options for the transports select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $transports = Transport::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($transports);
    }
}
