<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionDateLabel extends Model
{
    protected $fillable = ['name'];

    /**
     * W72 — label tanggal ditulis bebas saat periode disimpan; nama yang sama setelah
     * di-trim memakai baris yang ada. BR-FE-17 menuntut tenggat per tahap (mis.
     * "Batas Evaluasi"), jadi Operator harus bisa menamai label baru.
     */
    public static function named(string $name): self
    {
        return self::firstOrCreate(['name' => trim($name)]);
    }

    public function submissionDates()
    {
        return $this->hasMany(SubmissionDate::class);
    }
}
