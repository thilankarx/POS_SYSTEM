<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Crm\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CustomerResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->with('person')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('company_name', 'like', $term)
                    ->orWhereHas('person', fn (Builder $person) => $person
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)));
            })
            ->orderBy('company_name')
            ->paginate(max(1, min($request->integer('per_page', 25), 100)));

        return CustomerResource::collection($customers);
    }
}
