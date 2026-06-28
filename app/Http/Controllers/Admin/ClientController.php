<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::withCount('batches')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.clients.index', compact('clients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:120|unique:clients,name',
            'description' => 'nullable|string|max:255',
        ]);

        Client::create($validated);

        return back()->with('success', 'Cliente creado correctamente.');
    }

    public function show(Client $client)
    {
        $client->load(['batches' => fn($q) => $q->withCount('switches')->latest()]);

        return view('admin.clients.show', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:120|unique:clients,name,' . $client->id,
            'description' => 'nullable|string|max:255',
        ]);

        $client->update($validated);

        return back()->with('success', 'Cliente actualizado.');
    }

    public function destroy(Client $client)
    {
        // Desvincula los batches antes de eliminar (nullOnDelete en FK)
        $client->delete();

        return redirect()->route('admin.clients.index')
            ->with('success', 'Cliente eliminado.');
    }
}
