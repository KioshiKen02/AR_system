<?php

namespace App\Http\Controllers\MasterfileControllers;

use App\Events\NewCreated;
use App\Http\Controllers\Controller;
use App\Models\MasterfileModels\ChargeInvoiceType;
use App\Models\MasterfileModels\Item;
use App\Models\MasterfileModels\ItemPacking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::query();

        // Search functionality
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', '%' . $request->search . '%')
                    ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        // Type filters
        if ($request->type_filters) {
            $types = is_array($request->type_filters)
                ? $request->type_filters
                : explode(',', $request->type_filters);
            $query->whereIn('type', $types);
        }

        // Code sorting
        if ($request->code_sort) {
            $query->orderBy('code', $request->code_sort === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByRaw("LTRIM(code) ASC");
        }

        return Inertia::render('Item', [
            'items' => $query->paginate(10)->withQueryString(),
            'searchTerm' => $request->search,
            'filters' => [
                'code_sort' => $request->code_sort,
                'type_filters' => $request->type_filters ? (is_array($request->type_filters) ? $request->type_filters : explode(',', $request->type_filters)) : [],
            ],
            'broadcastChannel' => 'items',
        ]);
    }

    public function addItem(Request $request)
    {
        // sleep(1);

        //validate
        $fields = $request->validate(
            [
                'item_photo' => ['nullable', 'image'],
                'code' => ['required', 'string', 'min:8', Rule::unique('item', 'code')],
                'setup_date' => ['required', 'date', 'before_or_equal:today'],
                'name' => ['required', 'string', 'max:255', Rule::unique('item', 'name')],
                'description' => ['required', 'string'],
                'type' => ['required', 'string'],
                'acc_code' => ['required', 'string'],
            ],
            // custom messages
            [
                'code.unique' => 'This code already exists.',
                'name.unique' => 'This item already exists.',
            ]
        );

        $fields['name'] = ucwords(strtolower($fields['name']));

        if ($request->hasFile('item_photo')) {
            $file = $request->file('item_photo');

            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($file->getPathname());

            if ($file->getSize() > 300 * 1024) {
                $image->resize(800, 800, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            $filename = uniqid() . '.webp';
            $path = 'items/' . $filename;


            $encodedImage = $image->encode(new WebpEncoder(quality: 75));
            Storage::disk('public')->put($path, $encodedImage);

            $fields['item_photo'] = Storage::url($path);
        }

        Item::create($fields);
    }

    public function syncItem(Request $request)
    {
        try {
            // Change the ip address to dynamic based on the server ip address
            $response = Http::get('http://172.16.18.27/centralized_masterfile/masterfileController/ItemsController/fetchItems?fetchAll=true'); // this is for local
            if (!$response->successful()) {
                return response()->json(['error' => 'Failed to fetch items from API'], 500);
            }

            $apiItems = $response->json()['data'] ?? [];

            //DYNAMIC API LINK
            $user = auth()->user();
            $appName = $user && $user->appSetting ? $user->appSetting->app_name : config('app.name');
            switch ($appName) {
                case 'Bilar Breeder Local':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('13', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Bilar Breeder':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('13', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Gp Jagna':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('50', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Ice Plant':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('25', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Peanut Kisses':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('26', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Cortes Poultry':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('12', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Cortes Piggery':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('11', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Canhayupon Breeder':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('15', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Bilar Hatchery':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('14', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Lapsaon Breeder':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('16', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Rizal Breeder':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('43', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                // ubay server 
                case 'Feedmill':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('19', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Growout':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('20', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Cortes Fertilizer':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('42', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Ubay Fertilizer':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('22', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Piggery Untaga':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('23', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Demo Farm':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('21', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Dressing Plant':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('17', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Farmers Market':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('41', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Meat Processing':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('46', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                case 'Rendering':
                    $filteredApiItems = array_filter($apiItems, function ($item) {
                        return isset($item['item_bu'])
                            && in_array('18', explode(',', $item['item_bu']))
                            && $item['is_ar'] == '1';
                    });
                    break;
                default:
                    throw new \Exception("Unknown app name: {$appName}");
            }


            // $filteredApiItems = array_filter($apiItems, function ($item) {
            //     return isset($item['item_bu'])
            //         && in_array('13', explode(',', $item['item_bu']))
            //         && $item['item_trade_type'] == 'NON-TRADE';
            // });

            $syncedIds = [];
            DB::transaction(function () use ($filteredApiItems, &$syncedIds) {
                foreach ($filteredApiItems as $apiItem) {
                    $syncedIds[] = $apiItem['itemcode'];
                    Item::updateOrCreate(
                        ['code' => $apiItem['itemcode']],
                        [
                            'setup_date' => $apiItem['created_at'],
                            'name' => $apiItem['description'],
                        ]
                    );
                }
                Item::whereNotIn('code', $syncedIds)->delete();
            });

            event(new NewCreated('item'));

            return response()->json([
                'success' => true,
                'message' => 'Successfully synchronized ' . count($syncedIds) . ' items'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Synchronization failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function updateItem(Request $request, $id)
    {
        // Fix for route parameter mapping in tenant-prefixed routes
        $targetId = $request->route('id');
        if (!$targetId) {
             $targetId = $id;
        }

        if (!is_numeric($targetId)) {
             \Log::warning("Item Update: ID is non-numeric '{$targetId}'. Likely tenant prefix mismatch.");
        }

        $fields = $request->validate(
            [
                'item_photo' => ['nullable', 'image'],
                'code' => ['required', 'string', 'min:8'],
                'setup_date' => ['required', 'date', 'before_or_equal:today'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
                'type' => ['required', 'string'],
                'acc_code' => ['required', 'string'],
            ],
            [
                'code.unique' => 'This code already exists.',
                'name.unique' => 'This item already exists.',
            ]
        );

        $fields['name'] = ucwords(strtolower($fields['name']));

        $item = Item::findOrFail($targetId);

        if ($request->hasFile('item_photo')) {
            $file = $request->file('item_photo');

            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($file->getPathname());

            if ($file->getSize() > 300 * 1024) {
                $image->resize(800, 800, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            $filename = uniqid() . '.webp';
            $path = 'items/' . $filename;


            $encodedImage = $image->encode(new WebpEncoder(quality: 75));
            Storage::disk('public')->put($path, $encodedImage);

            if ($item->item_photo) {
                $oldPath = str_replace('/storage/', '', $item->item_photo);
                Storage::disk('public')->delete($oldPath);
            }

            $fields['item_photo'] = Storage::url($path);
        } else {
            $fields['item_photo'] = $item->item_photo;
        }

        $fields['created_by'] =  $request->user()->name;
        $item->update($fields);

        event(new NewCreated('item'));
    }

    public function destroy(Request $request, $id)
    {
        // Fix for route parameter mapping in tenant-prefixed routes
        $targetId = $request->route('id');
        if (!$targetId) {
             $targetId = $id;
        }

        if (!is_numeric($targetId)) {
             \Log::warning("Item Delete: ID is non-numeric '{$targetId}'. Likely tenant prefix mismatch.");
        }

        $item = Item::findOrFail($targetId);

        // Delete photo if it exists
        if ($item->item_photo) {
            $path = str_replace('/storage/', '', $item->item_photo); // Convert URL to storage path
            Storage::disk('public')->delete($path);
        }
        $item->delete();

        event(new NewCreated('item'));
    }

    public function getChargeInvoiceTypes() //for charge invoice type dropdown
    {
        $types = ChargeInvoiceType::pluck('ci_type');
        return response()->json($types);
    }

    public function getItemList(Request $request)
    {
        $type = $request->input('type');

        $query = Item::select('id', 'item_photo', 'code', 'name');

        if ($type) {
            $query->where('type', $type);
        }

        $list = $query->get();

        return response()->json($list);
    }

    public function getPackingList(Request $request)
    {
        $item_code = $request->input('itmcode');
        $price_group = $request->input('price_group');

        $item = Item::where('code', $item_code)->first();

        $list = ItemPacking::select('packing', 'price')
            ->where('item_id', $item->id)
            ->where('groupcode', $price_group)
            ->where('status', 'Available')
            ->get();

        return response()->json($list);
    }

    public function getAllItemList(Request $request)
    {
        try {
            // Get search term if provided
            $search = $request->input('search');

            $query = Item::query();

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            // Return only id and name columns
            $items = $query->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json($items);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'general' => 'No AR Outstanding Balance Data Found',
            ]);
        }
    }
}
