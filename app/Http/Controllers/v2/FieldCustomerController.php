<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FieldOperator;
use Illuminate\Http\Request;

class FieldCustomerController extends Controller
{
    private function getCurrentFieldOperatorId(Request $request): int
    {
        $user = $request->user();
        $fieldOperatorId = $user?->fieldOperator?->id
            ?? ($user ? FieldOperator::query()->where('user_id', $user->id)->value('id') : null);

        abort_unless($fieldOperatorId !== null, 403, 'No tienes una identidad operativa activa para acceder a este recurso.');

        return $fieldOperatorId;
    }

    public function options(Request $request)
    {
        $this->authorize('viewOperationalOptions', Customer::class);

        $fieldOperatorId = $this->getCurrentFieldOperatorId($request);

        $customers = Customer::query()
            ->select('id', 'name', 'field_operator_id', 'operational_status')
            ->where('field_operator_id', $fieldOperatorId)
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'operationalStatus' => $customer->operational_status,
            ]);

        return response()->json($customers->values());
    }
}
