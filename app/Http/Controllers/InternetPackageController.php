<?php

namespace App\Http\Controllers;

use App\Models\InternetPackage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternetPackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $status = $validated['status'] ?? '';

        $packages = InternetPackage::query()
            ->with('mikrotikDevice')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('package_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('download_speed', 'like', "%{$search}%")
                        ->orWhere('upload_speed', 'like', "%{$search}%")
                        ->orWhere('mikrotik_profile', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('internet-packages.index', compact(
            'packages',
            'search',
            'status'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('internet-packages.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_code' => 'required|string|max:50|unique:internet_packages,package_code',
            'name' => 'required|string|max:255',
            'download_speed' => 'required|string|max:50',
            'upload_speed' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'mikrotik_profile' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        InternetPackage::create($validated);

        return redirect()
            ->route('internet-packages.index')
            ->with('success', 'Paket internet berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InternetPackage $internetPackage)
    {
        return view('internet-packages.edit', compact('internetPackage'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InternetPackage $internetPackage)
    {
        $validated = $request->validate([
            'package_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('internet_packages', 'package_code')
                    ->ignore($internetPackage->id),
            ],
            'name' => 'required|string|max:255',
            'download_speed' => 'required|string|max:50',
            'upload_speed' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'mikrotik_profile' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $internetPackage->update($validated);

        return redirect()
            ->route('internet-packages.index')
            ->with('success', 'Paket internet berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InternetPackage $internetPackage)
    {
        $internetPackage->update([
            'status' => 'inactive',
        ]);

        return redirect()
            ->route('internet-packages.index')
            ->with('success', 'Paket internet berhasil dinonaktifkan tanpa menghapus histori.');
    }
}
