<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Problem;
use App\Http\Requests\PenyesuaianStokRequest;
use App\Http\Resources\ProdukResource;
use App\Models\Produk;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokController extends Controller
{
    public function __invoke(
        PenyesuaianStokRequest $request,
        Produk $produk,
    ) {
        $input = $request->validated();
        $operationId = strtolower($input['operation_id']);
        $perubahan = (int) $input['perubahan'];

        $hash = hash('sha256', json_encode([
            'sku' => $produk->sku,
            'perubahan' => $perubahan,
            'alasan' => $input['alasan'],
        ], JSON_THROW_ON_ERROR));

        try {
            $hasil = DB::transaction(function () use (
                $request,
                $produk,
                $operationId,
                $perubahan,
                $hash,
            ) {
                $terkini = Produk::whereKey($produk->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $operasi = DB::table('operasi_stok')
                    ->where('operation_id', $operationId)
                    ->first();

                if ($operasi) {
                    return $this->hasilSebelumnya(
                        $operasi,
                        $hash,
                        $request,
                    );
                }

                if ($terkini->stok + $perubahan < 0) {
                    $this->konflik(
                        $request,
                        'stok-tidak-cukup',
                        'Stok tidak cukup untuk perubahan yang diminta.',
                    );
                }

                $terkini->increment('stok', $perubahan);
                $terkini->refresh();

                \App\Models\Outbox::catat(
                    new \App\Events\StokDisesuaikan(
                        operationId: $operationId,
                        sku: $terkini->sku,
                        perubahan: $perubahan,
                        alasan: $request->input('alasan'),
                        stokSesudah: $terkini->stok,
                        terjadiPada: $terkini->updated_at
                            ->toIso8601String(),
                    )
                );

                $hasil = [
                    'data' => (new ProdukResource($terkini))
                        ->resolve($request),
                ];

                DB::table('operasi_stok')->insert([
                    'operation_id' => $operationId,
                    'sku' => $terkini->sku,
                    'payload_hash' => $hash,
                    'hasil' => json_encode(
                        $hasil,
                        JSON_THROW_ON_ERROR,
                    ),
                ]);

                return $hasil;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $operasi = DB::table('operasi_stok')
                ->useWritePdo()
                ->where('operation_id', $operationId)
                ->first();

            if (! $operasi) {
                throw $e;
            }

            $hasil = $this->hasilSebelumnya(
                $operasi,
                $hash,
                $request,
            );
        }

        return response()->json($hasil);
    }

    private function hasilSebelumnya(
        object $operasi,
        string $hash,
        Request $request,
    ): array {
        if (! hash_equals($operasi->payload_hash, $hash)) {
            $this->konflik(
                $request,
                'operation-id-konflik',
                'Operation_id sudah digunakan dengan isi berbeda.',
            );
        }

        return json_decode(
            $operasi->hasil,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function konflik(
        Request $request,
        string $slug,
        string $detail,
    ): never {
        throw new HttpResponseException(
            Problem::response(
                $request,
                409,
                $slug,
                'Penyesuaian stok ditolak',
                $detail,
            )
        );
    }
}
