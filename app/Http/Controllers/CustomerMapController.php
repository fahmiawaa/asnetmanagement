<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerMapController extends Controller
{
    public function index(Request $request)
    {
        $areaOptions = [
            'Desa Banyuputih, Kecamatan Banyuputih, Kabupaten Batang, Jawa Tengah',
            'Desa Sembung, Kecamatan Banyuputih, Kabupaten Batang, Jawa Tengah',
            'Desa Bulu, Kecamatan Banyuputih, Kabupaten Batang, Jawa Tengah',
        ];
        $areaCenters = [
            $areaOptions[0] => [-6.950, 109.935],
            $areaOptions[1] => [-6.944, 109.955],
            $areaOptions[2] => [-6.976, 109.918],
        ];
        $storedAreas = Customer::query()
            ->whereNotNull('address')
            ->pluck('address')
            ->map(fn (string $address) => trim(Str::before($address, ' —')))
            ->filter()
            ->values()
            ->all();
        $areaOptions = collect($areaOptions)
            ->merge($storedAreas)
            ->unique()
            ->values()
            ->all();

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'area' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $area = trim((string) ($validated['area'] ?? ''));

        $customers = Customer::query()
            ->with('internetPackage')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($area !== '', fn ($query) => $query->where('address', 'like', "%{$area}%"))
            ->orderBy('name')
            ->get();

        $customersData = $customers->map(function (Customer $customer): array {
            return [
                'id' => $customer->id,
                'code' => $customer->customer_code,
                'name' => $customer->name,
                'address' => $customer->address,
                'phone' => $customer->whatsapp_number,
                'status' => $customer->status,
                'package' => $customer->internetPackage?->name,
                'latitude' => (float) $customer->latitude,
                'longitude' => (float) $customer->longitude,
                'url' => route('customers.show', $customer),
            ];
        })->values();

        $unmappedCount = Customer::query()
            ->where(function ($query) {
                $query
                    ->whereNull('latitude')
                    ->orWhereNull('longitude');
            })
            ->count();

        $statusCounts = Customer::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('map.index', [
            'customers' => $customersData,
            'search' => $search,
            'area' => $area,
            'areaOptions' => $areaOptions,
            'areaCenters' => $areaCenters,
            'mappedCount' => $customersData->count(),
            'unmappedCount' => $unmappedCount,
            'statusCounts' => $statusCounts,
        ]);
    }
}
