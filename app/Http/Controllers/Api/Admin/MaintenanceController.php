<?php

// app/Http/Controllers/Api/Admin/MaintenanceController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:system administration']);
    }

    public function index(): JsonResponse
    {
        $maintenance = MaintenanceSetting::current();
        
        return response()->json([
            'maintenance' => $maintenance,
            'status' => $maintenance->getMaintenanceStatus(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string'],
            'scheduled_start' => ['sometimes', 'nullable', 'date'],
            'scheduled_end' => ['sometimes', 'nullable', 'date', 'after:scheduled_start'],
            'allowed_ips' => ['sometimes', 'array'],
            'allowed_ips.*' => ['ip'],
            'allowed_roles' => ['sometimes', 'array'],
            'allowed_roles.*' => ['string', 'exists:roles,name'],
            'allow_admin_access' => ['sometimes', 'boolean'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'social_links' => ['sometimes', 'array'],
            'estimated_duration' => ['sometimes', 'nullable', 'string', 'max:100'],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $maintenance = MaintenanceSetting::current();
        $maintenance->update($request->validated());

        // Log maintenance mode change
        auth()->user()->logSecurityEvent('maintenance_mode_updated', [
            'is_enabled' => $maintenance->is_enabled,
            'scheduled_start' => $maintenance->scheduled_start,
            'scheduled_end' => $maintenance->scheduled_end,
        ]);

        return response()->json([
            'message' => 'Maintenance settings updated successfully',
            'maintenance' => $maintenance->fresh(),
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string'],
            'duration' => ['sometimes', 'integer', 'min:1'], // minutes
            'scheduled_start' => ['sometimes', 'nullable', 'date'],
        ]);

        $maintenance = MaintenanceSetting::current();
        
        $updates = ['is_enabled' => true];
        
        if ($request->title) {
            $updates['title'] = $request->title;
        }
        
        if ($request->message) {
            $updates['message'] = $request->message;
        }

        if ($request->duration) {
            $updates['scheduled_start'] = $request->scheduled_start ? 
                \Carbon\Carbon::parse($request->scheduled_start) : now();
            $updates['scheduled_end'] = \Carbon\Carbon::parse($updates['scheduled_start'])
                ->addMinutes($request->duration);
            $updates['estimated_duration'] = $request->duration . ' minutes';
        }

        $maintenance->update($updates);

        // Log maintenance mode enabled
        auth()->user()->logSecurityEvent('maintenance_mode_enabled', [
            'scheduled_start' => $maintenance->scheduled_start,
            'scheduled_end' => $maintenance->scheduled_end,
            'duration' => $request->duration,
        ]);

        return response()->json([
            'message' => 'Maintenance mode enabled',
            'maintenance' => $maintenance->fresh(),
        ]);
    }

    public function disable(): JsonResponse
    {
        $maintenance = MaintenanceSetting::current();
        $maintenance->update([
            'is_enabled' => false,
            'scheduled_start' => null,
            'scheduled_end' => null,
        ]);

        // Log maintenance mode disabled
        auth()->user()->logSecurityEvent('maintenance_mode_disabled');

        return response()->json([
            'message' => 'Maintenance mode disabled',
            'maintenance' => $maintenance->fresh(),
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'scheduled_start' => ['required', 'date', 'after:now'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'title' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string'],
        ]);

        $maintenance = MaintenanceSetting::current();
        
        $start = \Carbon\Carbon::parse($request->scheduled_start);
        $end = \Carbon\Carbon::parse($request->scheduled_end);
        $duration = $start->diffInMinutes($end);

        $maintenance->update([
            'is_enabled' => true,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'estimated_duration' => $duration . ' minutes',
            'title' => $request->title ?? $maintenance->title,
            'message' => $request->message ?? $maintenance->message,
        ]);

        // Log scheduled maintenance
        auth()->user()->logSecurityEvent('maintenance_scheduled', [
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'duration_minutes' => $duration,
        ]);

        return response()->json([
            'message' => 'Maintenance scheduled successfully',
            'maintenance' => $maintenance->fresh(),
        ]);
    }
}