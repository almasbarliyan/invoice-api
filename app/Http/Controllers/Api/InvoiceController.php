<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use DB;

/**
 * @OA\Tag(
 *     name="Invoice",
 *     description="API untuk manajemen invoice"
 * )
 * @OA\Info(
 *     version="1.0.0",
 *     title="Invoice API"
 * )
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="API Server"
 * )
 */

class InvoiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     summary="Get all invoices",
     *     tags={"Invoice"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *     )
     * )
     */
    public function index(Request $request)
    {
        $invoices = Invoice::with('items')
            ->where('user_id', Auth::id())
            ->paginate(10);

        return response()->json($invoices);
    }

    /**
     *  @OA\Post(
     *     path="/api/invoices",
     *     tags={"Invoice"},
     *     summary="Create a new invoice",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "due_date", "items"},
     *             @OA\Property(property="customer_id", type="integer", example=1),
     *             @OA\Property(property="due_date", type="string", format="date", example="2025-08-10"),
     *            @OA\Property(property="items", type="array", @OA\Items(
     *                 @OA\Property(property="name", type="string", example="Item Name"),
     *                 @OA\Property(property="qty", type="integer", example=2),
     *                 @OA\Property(property="price", type="number", format="float", example=100.00)
     *             )),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Success"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Gagal membuat invoice"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'due_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal','errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $invoice = Invoice::create([
                'user_id' => Auth::id(),
                'customer_id' => $request->customer_id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'due_date' => $request->due_date,
                'total' => 0,
            ]);

            $total = 0;
            foreach ($request->items as $item) {
                $subtotal = $item['qty'] * $item['price'];
                $total += $subtotal;
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_name' => $item['name'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $subtotal,
                ]);
            }

            $invoice->update(['total' => $total]);
            DB::commit();
            return response()->json($invoice->load('items'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     *  @OA\Get(
     *     path="/api/invoices/{id}",
     *     tags={"Invoice"},
     *     summary="Show invoice details by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the invoice to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice tidak ditemukan"
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $invoice = Invoice::where('user_id', Auth::id())->findOrFail($id);
            return response()->json($invoice);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invoice tidak ditemukan'
            ], 404);
        }

        return response()->json($invoice);
    }

    /**
     *  @OA\Put(
     *     path="/api/invoices/{id}",
     *     tags={"Invoice"},
     *     summary="Update invoice",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the invoice to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"due_date", "items"},
     *             @OA\Property(property="due_date", type="string", format="date", example="2025-08-10"),
     *            @OA\Property(property="items", type="array", @OA\Items(
     *                 @OA\Property(property="name", type="string", example="Item Name"),
     *                 @OA\Property(property="qty", type="integer", example=2),
     *                 @OA\Property(property="price", type="number", format="float", example=100.00)
     *             )),
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice tidak ditemukan"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Gagal memperbarui invoice"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $invoice = Invoice::where('user_id', Auth::id())->findOrFail($id);
            return response()->json($invoice);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invoice tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'due_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal','errors' => $validator->errors()], 422);
        }
        DB::beginTransaction();
        try {
            $invoice->update(['due_date' => $request->due_date, 'total' => 0]);

            InvoiceItem::where('invoice_id', $invoice->id)->delete();

            $total = 0;
            foreach ($request->items as $item) {
                $subtotal = $item['qty'] * $item['price'];
                $total += $subtotal;
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_name' => $item['name'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $subtotal,
                ]);
            }

            $invoice->update(['total' => $total]);
            DB::commit();
            return response()->json($invoice->load('items'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memperbarui invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     *  @OA\Delete(
     *     path="/api/invoices/{id}",
     *     tags={"Invoice"},
     *     operationId="deleteInvoice",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the invoice to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     summary="Delete invoice by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Invoice berhasil dihapus"
     *     ),
     * )
     */
    public function destroy($id)
    {
        $invoice = Invoice::where('user_id', Auth::id())->findOrFail($id);
        $invoice->items()->delete();
        $invoice->delete();

        return response()->json(['message' => 'Invoice berhasil dihapus'], 200);
    }
}
