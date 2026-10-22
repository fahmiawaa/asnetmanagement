<?php

namespace App\Http\Controllers;

use App\Models\DeviceTask;
use App\Models\OntDevice;
use App\Services\DeviceTaskService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DeviceTaskController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DeviceTask::class);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(DeviceTask::STATUSES)],
            'type' => ['nullable', Rule::in(DeviceTask::TYPES)],
        ]);

        $status = $validated['status'] ?? '';
        $type = $validated['type'] ?? '';

        $tasks = DeviceTask::query()
            ->with([
                'ontDevice:id,serial_number,genieacs_device_id,customer_id,connection_status',
                'ontDevice.customer:id,customer_code,name',
                'requestedBy:id,name,role',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->orderByDesc('id')
            ->paginate(max(1, (int) config('genieacs.ont_list_per_page')))
            ->withQueryString();

        return view('device-tasks.index', [
            'tasks' => $tasks,
            'status' => $status,
            'type' => $type,
            'typeLabels' => $this->typeLabels(),
            'statusLabels' => $this->statusLabels(),
            'tasksEnabled' => (bool) config('genieacs.tasks.enabled'),
        ]);
    }

    public function summon(
        Request $request,
        OntDevice $ontDevice,
        DeviceTaskService $taskService,
    ): RedirectResponse {
        return $this->storeTask($request, $ontDevice, DeviceTask::TYPE_SUMMON, $taskService);
    }

    public function reboot(
        Request $request,
        OntDevice $ontDevice,
        DeviceTaskService $taskService,
    ): RedirectResponse {
        return $this->storeTask($request, $ontDevice, DeviceTask::TYPE_REBOOT, $taskService);
    }

    public function wifi(
        Request $request,
        OntDevice $ontDevice,
        DeviceTaskService $taskService,
    ): RedirectResponse {
        return $this->storeTask($request, $ontDevice, DeviceTask::TYPE_SET_WIFI, $taskService);
    }

    public function cancel(
        Request $request,
        DeviceTask $deviceTask,
        DeviceTaskService $taskService,
    ): RedirectResponse {
        Gate::authorize('cancel', $deviceTask);

        $taskService->cancel($deviceTask, $request->user());

        return redirect()
            ->route('device-tasks.index')
            ->with('success', 'Task dibatalkan.');
    }

    private function storeTask(
        Request $request,
        OntDevice $ontDevice,
        string $type,
        DeviceTaskService $taskService,
    ): RedirectResponse {
        Gate::authorize('runTask', $ontDevice);

        $validated = $request->validate([
            'ssid' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'max:63'],
            'profile_id' => ['nullable', 'integer', Rule::exists('ont_parameter_profiles', 'id')],
        ]);

        $task = $taskService->request(
            ontDevice: $ontDevice,
            type: $type,
            user: $request->user(),
            wifi: [
                'ssid' => $validated['ssid'] ?? '',
                'password' => $validated['password'] ?? '',
                'profile_id' => $validated['profile_id'] ?? null,
            ],
        );

        return redirect()
            ->route('ont-devices.show', $ontDevice)
            ->with('success', "Task {$task->type} dikirim ke antrean dan menunggu verifikasi.");
    }

    /**
     * @return array<string, string>
     */
    private function typeLabels(): array
    {
        return [
            DeviceTask::TYPE_SUMMON => 'Summon',
            DeviceTask::TYPE_REBOOT => 'Reboot',
            DeviceTask::TYPE_SET_WIFI => 'Ubah WiFi',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        return [
            DeviceTask::STATUS_PENDING => 'Pending',
            DeviceTask::STATUS_PROCESSING => 'Processing',
            DeviceTask::STATUS_ACCEPTED => 'Accepted',
            DeviceTask::STATUS_VERIFIED => 'Verified',
            DeviceTask::STATUS_FAILED => 'Failed',
            DeviceTask::STATUS_EXPIRED => 'Expired',
            DeviceTask::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
