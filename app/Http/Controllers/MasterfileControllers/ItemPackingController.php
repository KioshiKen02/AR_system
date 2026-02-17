<?php

namespace App\Http\Controllers\MasterfileControllers;

use App\Http\Controllers\Controller;
use App\Models\MasterfileModels\Item;
use App\Models\MasterfileModels\ItemPacking;
use App\Models\MasterfileModels\PackingType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ItemPackingController extends Controller
{

    public function show(Request $request, $item)
    {
        $targetId = $request->route('item') ?? $item;
        $item = Item::on('tenant')->findOrFail($targetId);
        return response()->json(
            $item->packings()->select('groupcode', 'packing', 'price', 'quantity', 'status')->get()
        );
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer'],
            'packings' => ['nullable', 'array'],
            'packings.*.groupcode' => ['required_with:packings', 'string'],
            'packings.*.packing' => ['required_with:packings', 'string'],
            'packings.*.price' => ['required_with:packings', 'numeric'],
            'packings.*.quantity' => ['required_with:packings', 'integer'],
            'packings.*.status' => ['required_with:packings', 'string'],
        ]);

        $itemExists = Item::on('tenant')->where('id', $validated['item_id'])->exists();
        if (!$itemExists) {
            throw ValidationException::withMessages(['item_id' => 'Selected item does not exist']);
        }

        if (isset($validated['packings'])) {
            $combinations = array_map(function ($packing) {
                return strtolower(trim($packing['groupcode'] . '|' . $packing['packing']));
            }, $validated['packings']);

            if (count($combinations) !== count(array_unique($combinations))) {
                throw ValidationException::withMessages([
                    'packings' => 'Duplicate groupcode and packing combination is not allowed'
                ]);
            }
        }

        DB::connection('tenant')->transaction(function () use ($validated, $request) {
            $itemID = $validated['item_id'];

            // If packings is empty or not provided, delete existing records
            if (empty($validated['packings'])) {
                ItemPacking::on('tenant')->where('item_id', $itemID)->delete();
                return;
            }

            // Delete old entries
            ItemPacking::on('tenant')->where('item_id', $itemID)->delete();

            // Prepare data for batch insert
            $newPackings = array_map(function ($packing) use ($itemID) {
                return [
                    'item_id' => $itemID,
                    'groupcode' => $packing['groupcode'],
                    'packing' => $packing['packing'],
                    'price' => $packing['price'],
                    'quantity' => $packing['quantity'],
                    'status' => $packing['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $validated['packings']);

            // Insert all in one go
            ItemPacking::on('tenant')->insert($newPackings);

            $item = Item::on('tenant')->find($validated['item_id']);
            if ($item) {
                $item->update([
                    'created_by' => $request->user()->name,
                ]);
            }
        });
        
        return redirect()->back()->with('success', true);
    }

    public function destroy(Request $request, $itemPacking)
    {
        $targetId = $request->route('itemPacking') ?? $itemPacking;

        if (!is_numeric($targetId)) {
            throw ValidationException::withMessages(['id' => 'Invalid item packing id']);
        }

        try {
            $model = ItemPacking::on('tenant')->findOrFail($targetId);
            $model->delete();
            return redirect()->back()->with('success', true);
        } catch (ModelNotFoundException $e) {
            return redirect()->back()->withErrors(['general' => 'Item packing not found']);
        }
    }

    public function getPackingTypes()
    {
        $types = PackingType::on('tenant')->pluck('packing_type');
        return response()->json($types);
    }
}
