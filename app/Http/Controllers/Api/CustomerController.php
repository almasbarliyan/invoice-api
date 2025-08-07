<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Http\Resources\CustomerResource;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::where('user_id', auth()->id())->get();
        return CustomerResource::collection($customers);
    }

    /**
     * @OA\Post(
     *     path="/api/customers",
     *     summary="Create a new customer",
     *     tags={"Customer"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "phone"},
     *             @OA\Property(property="name", type="string", example="PT. Maju Jaya"),
     *             @OA\Property(property="email", type="string", example="majujaya@example.com"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Customer created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
     */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $customer = Customer::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
        ]);

        return new CustomerResource($customer);
    }

    public function show(Customer $customer)
    {
        return new CustomerResource($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $customer->update($validated);

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}
