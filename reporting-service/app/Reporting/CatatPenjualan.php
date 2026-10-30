<?php

namespace App\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CatatPenjualan
{
    public function __invoke(array $event, string $messageId): bool
    {
        if (! Str::isUuid($messageId)) {
            throw new PesanTidakValid(
                'Message_id wajib berupa UUID.'
            );
        }

        $validator = Validator::make($event, [
            'schema_version' => ['required', 'integer', 'in:1'],
            'operation_id' => ['required', 'uuid'],
            'sku_internal' => ['required', 'string', 'max:7'],
            'perubahan' => ['required', 'integer', 'not_in:0'],
            'alasan' => ['required', 'in:penjualan,restok,koreksi'],
            'stok_sesudah' => ['required', 'integer', 'min:0'],
            'terjadi_pada' => [
                'required',
                'date_format:Y-m-d\TH:i:sP',
            ],
        ]);

        if ($validator->fails()) {
            throw new PesanTidakValid(
                'Payload tidak memenuhi kontrak event versi 1.'
            );
        }

        $data = $validator->validated();

        foreach (['schema_version', 'perubahan', 'stok_sesudah'] as $field) {
            if (! is_int($data[$field])) {
                throw new PesanTidakValid(
                    'Field angka pada payload harus bertipe integer.'
                );
            }
        }

        if ($data['alasan'] !== 'penjualan') {
            return false;
        }

        if ($data['perubahan'] >= 0
            || $data['perubahan'] === PHP_INT_MIN) {
            throw new PesanTidakValid(
                'Event penjualan harus mempunyai perubahan negatif yang valid.'
            );
        }

        ksort($data);

        $hash = hash(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        try {
            return DB::transaction(function () use (
                $data,
                $messageId,
                $hash,
            ) {
                $lama = DB::table('inbox')
                    ->where('message_id', $messageId)
                    ->lockForUpdate()
                    ->first();

                if ($lama) {
                    return $this->periksaDuplikat($lama, $hash);
                }

                DB::table('inbox')->insert([
                    'message_id' => $messageId,
                    'payload_hash' => $hash,
                ]);

                DB::table('jurnal_penjualan')->insert([
                    'message_id' => $messageId,
                    'operation_id' => $data['operation_id'],
                    'sku_internal' => $data['sku_internal'],
                    'jumlah' => -$data['perubahan'],
                    'terjadi_pada' => CarbonImmutable::createFromFormat(
                        'Y-m-d\TH:i:sP',
                        $data['terjadi_pada'],
                    )->utc()->format('Y-m-d H:i:s'),
                ]);

                return true;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $lama = DB::table('inbox')
                ->useWritePdo()
                ->where('message_id', $messageId)
                ->first();

            if (! $lama) {
                throw $e;
            }

            return $this->periksaDuplikat($lama, $hash);
        }
    }

    private function periksaDuplikat(
        object $lama,
        string $hash,
    ): bool {
        if (! hash_equals($lama->payload_hash, $hash)) {
            throw new PesanTidakValid(
                'Message_id sudah digunakan dengan payload berbeda.'
            );
        }

        return false;
    }
}
