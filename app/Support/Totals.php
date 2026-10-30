<?php

namespace App\Support;

class Totals
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float, discount: float, tax_rate: float, tax_amount: float, total: float}
     */
    public static function fromLines(array $lines, float $discount, float $taxRate): array
    {
        $normalized = [];
        $subtotal = 0.0;

        foreach (array_values($lines) as $index => $line) {
            $quantity = round((float) ($line['quantity'] ?? 0), 2);
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
            $lineTotal = round($quantity * $unitPrice, 2);
            $subtotal += $lineTotal;

            $normalized[] = [
                'position' => $index,
                'designation' => trim((string) ($line['designation'] ?? '')),
                'detail' => isset($line['detail']) && $line['detail'] !== '' ? trim((string) $line['detail']) : null,
                'quantity' => $quantity,
                'unit' => $line['unit'] ?? 'u',
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        $subtotal = round($subtotal, 2);
        $discount = round(min(max($discount, 0), $subtotal), 2);
        $base = round($subtotal - $discount, 2);
        $taxAmount = round($base * $taxRate / 100, 2);

        return [
            'lines' => $normalized,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => $taxAmount,
            'total' => round($base + $taxAmount, 2),
        ];
    }
}
