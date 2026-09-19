<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-SM-20 — anggota mahasiswa sebuah pengajuan.
 *
 * Berbeda dari {@see ResearchMember} yang menunjuk `UserProfile`: mahasiswa
 * tidak punya akun di SIMPAS, jadi datanya tersimpan apa adanya. Keduanya
 * sengaja tidak disatukan — menyatukannya memaksa salah satu pihak memakai
 * bentuk yang bukan miliknya.
 *
 * @property int $id
 * @property int $form_submission_id
 * @property string $name
 * @property string $student_id
 * @property string|null $study_program
 */
class StudentMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_submission_id',
        'name',
        'student_id',
        'study_program',
    ];

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }
}
