<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexActivityLogRequest;
use App\Http\Resources\v2\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(IndexActivityLogRequest $request)
    {
        $this->authorize('viewAny', ActivityLog::class);

        $query = ActivityLog::query();

        /* usersIds */
        if ($request->has('users')) {
            $query->whereIn('user_id', $request->users);
        }

        /* ipAddresses */
        if ($request->has('ipAddresses')) {
            $query->whereIn('ip_address', $request->ipAddresses);
        }

        /* countries */
        if ($request->has('countries')) {
            $query->whereIn('country', $request->countries);
        }

        /* citie like*/
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        /* path like */
        if ($request->has('path')) {
            $query->where('path', 'like', '%' . $request->path . '%');
        }

        /* createdAt */
        /* Dates */
        if ($request->has('dates')) {
            $dates = $request->input('dates');
            if (isset($dates['start'])) {
                $startDate = $dates['start'];
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $query->where('created_at', '>=', $startDate);
            }

            if (isset($dates['end'])) {
                $endDate = $dates['end'];
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $query->where('created_at', '<=', $endDate);
            }
        }

        /* order by created_at */
        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 10);
        $activityLogs = $query->paginate($perPage);

        return ActivityLogResource::collection($activityLogs);
    }
}
