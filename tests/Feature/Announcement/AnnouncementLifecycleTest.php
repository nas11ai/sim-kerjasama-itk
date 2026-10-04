<?php

// Penerbitan, revisi, dan penarikan pengumuman lewat Action beraudit (RPT-AUDIT-07).

use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::findOrCreate('Super Admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
});

function announcementWithFile(string $title): array
{
    $announcement = Announcement::create([
        'title' => $title,
        'content' => 'Isi.',
        'type' => 'public',
        'created_by' => User::factory()->create()->id,
    ]);
    $path = "announcements/{$title}.pdf";
    Storage::disk('public')->put($path, 'isi');
    $file = $announcement->announcementFiles()->create([
        'file_name' => "{$title}.pdf",
        'file_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 3,
    ]);

    return [$announcement, $file];
}

function announcementPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Judul',
        'content' => 'Isi pengumuman.',
        'type' => 'public',
    ], $overrides);
}

it('keeps attachments of other announcements when deleted_files names their ids', function () {
    [$mine] = announcementWithFile('milik-saya');
    [, $foreign] = announcementWithFile('milik-lain');

    $this->actingAs($this->admin)
        ->put(route('admin.announcements.update', $mine), announcementPayload(['deleted_files' => [$foreign->id]]))
        ->assertRedirect();

    expect(AnnouncementFile::find($foreign->id))->not->toBeNull();
    Storage::disk('public')->assertExists($foreign->file_path);
});

it('removes an own attachment, row and disk file, when it is listed in deleted_files', function () {
    [$mine, $file] = announcementWithFile('milik-saya');

    $this->actingAs($this->admin)
        ->put(route('admin.announcements.update', $mine), announcementPayload(['deleted_files' => [$file->id]]))
        ->assertRedirect();

    expect(AnnouncementFile::find($file->id))->toBeNull();
    Storage::disk('public')->assertMissing($file->file_path);
});

it('removes attachment files from the public disk when the announcement is retracted', function () {
    [$announcement, $file] = announcementWithFile('akan-ditarik');

    $this->actingAs($this->admin)
        ->delete(route('admin.announcements.destroy', $announcement))
        ->assertRedirect();

    expect(Announcement::find($announcement->id))->toBeNull()
        ->and(AnnouncementFile::find($file->id))->toBeNull();
    Storage::disk('public')->assertMissing($file->file_path);
});

it('publishes an announcement with its attachments and audits it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'files' => [UploadedFile::fake()->create('lampiran.pdf', 10, 'application/pdf')],
        ]))
        ->assertRedirect(route('admin.announcements.index'));

    $announcement = Announcement::firstOrFail();
    expect($announcement->created_by)->toBe($this->admin->id)
        ->and($announcement->announcementFiles)->toHaveCount(1);
    Storage::disk('public')->assertExists($announcement->announcementFiles->firstOrFail()->file_path);

    $entry = Activity::inLog(AuditTrail::LOG_NAME)->where('description', 'Pengumuman diterbitkan')->firstOrFail();
    expect($entry->subject_id)->toBe($announcement->id)
        ->and($entry->causer_id)->toBe($this->admin->id);
});

it('audits a revision with the old and the new values', function () {
    [$announcement] = announcementWithFile('lama');

    $this->actingAs($this->admin)
        ->put(route('admin.announcements.update', $announcement), announcementPayload(['title' => 'Baru']))
        ->assertRedirect();

    $entry = Activity::inLog(AuditTrail::LOG_NAME)->where('description', 'Pengumuman direvisi')->firstOrFail();
    expect($entry->properties['old']['title'])->toBe('lama')
        ->and($entry->properties['new']['title'])->toBe('Baru');
});

it('audits a retraction', function () {
    [$announcement] = announcementWithFile('ditarik');

    $this->actingAs($this->admin)
        ->delete(route('admin.announcements.destroy', $announcement))
        ->assertRedirect();

    $entry = Activity::inLog(AuditTrail::LOG_NAME)->where('description', 'Pengumuman ditarik')->firstOrFail();
    expect($entry->subject_id)->toBe($announcement->id)
        ->and($entry->properties['old']['title'])->toBe('ditarik');
});
