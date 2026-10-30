<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TambahProdukRequest;
use App\Http\Resources\ProdukResource;
use App\Models\Produk;
use Illuminate\Http\Request;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'limit' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Produk::orderBy('sku');

        if (array_key_exists('limit', $input)) {
            $query->limit((int) $input['limit']);
        }

        return ProdukResource::collection($query->get());
    }

    public function show(Produk $produk)
    {
        return new ProdukResource($produk);
    }

    public function store(TambahProdukRequest $request)
    {
        $produk = Produk::create(
            $request->validated() + ['stok' => 0]
        );

        return (new ProdukResource($produk))
            ->response()
            ->setStatusCode(201)
            ->header(
                'Location',
                url("/api/v1/produk/{$produk->sku}")
            );
    }
}
