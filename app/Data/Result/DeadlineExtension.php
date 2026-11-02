<?php

namespace App\Data\Result;

/**
 * Hasil penyelarasan tenggat periode (BR-SCH-08): apakah ada batas yang mundur, dan
 * berapa pemilik draft aktif yang diberi tahu. Dipisah supaya "tidak diperpanjang"
 * tidak tertukar dengan "diperpanjang tanpa draft" (SCH-RULE-13, W73).
 */
final class DeadlineExtension
{
    public function __construct(
        public readonly bool $extended,
        public readonly int $notified,
    ) {}
}
