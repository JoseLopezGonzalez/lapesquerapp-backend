<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CustomerListService
{
    /**
     * Lista clientes con filtros y paginación.
     *
     * @param  Request  $request  Request con query params ya validados (IndexCustomerRequest)
     * @return LengthAwarePaginator
     */
    public static function list(Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $query = Customer::query()
            ->with(['payment_term', 'salesperson', 'fieldOperator', 'country', 'transport']);

        if ($user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('salesperson_id', $user->salesperson->id);
        }

        if ($request->filled('id')) {
            $query->where('id', $request->input('id'));
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->filled('vatNumber')) {
            $query->where('vat_number', $request->input('vatNumber'));
        }

        if ($request->filled('paymentTerms')) {
            $query->whereIn('payment_term_id', $request->input('paymentTerms'));
        }

        if ($request->filled('salespeople')) {
            $query->whereIn('salesperson_id', $request->input('salespeople'));
        }

        if ($request->boolean('withoutSalesperson')) {
            $query->whereNull('salesperson_id');
        }

        if ($request->filled('fieldOperatorId')) {
            $query->where('field_operator_id', $request->integer('fieldOperatorId'));
        }

        if ($request->filled('operationalStatus')) {
            $query->where('operational_status', $request->input('operationalStatus'));
        }

        if ($request->filled('countries')) {
            $query->whereIn('country_id', $request->input('countries'));
        }

        $query->orderBy('name', 'asc');

        $perPage = min((int) $request->input('perPage', 10), 100);

        return $query->paginate($perPage);
    }
}
