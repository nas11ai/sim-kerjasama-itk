<?php

// fe-v2-design §4.7 K6a — gambar sisipan pengumuman hanya lewat unggahan: jenis dibatasi,
// disandi ulang (metadata hilang), disimpan bernama acak, dan diaudit (BR-RPT-09).

use App\Models\User;
use App\Support\Announcement\InlineImageSource;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(PermissionSeeder::class);
    $this->operator = User::factory()->create();
    $this->operator->assignRole('operator');
});

function uploadImage(User $actor, UploadedFile $image)
{
    return test()->actingAs($actor)->postJson(route('admin.announcements.images.store'), ['image' => $image]);
}

/** JPEG asli GD dengan segmen APP1 "Exif" disisipkan setelah penanda SOI. */
function jpegWithExif(): UploadedFile
{
    $canvas = imagecreatetruecolor(20, 20);
    ob_start();
    imagejpeg($canvas);
    $jpeg = (string) ob_get_clean();
    $payload = "Exif\0\0MM\0*\0\0\0\x08\0\0GPS-RAHASIA";
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    $path = tempnam(sys_get_temp_dir(), 'exif');
    file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

    return new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true);
}

it('unggah gambar oleh operator menjawab url relatif', function () {
    $response = uploadImage($this->operator, UploadedFile::fake()->image('poster.png', 300, 200))
        ->assertCreated();

    $url = $response->json('url');
    expect($url)->toMatch('#^/storage/announcements/inline/[A-Za-z0-9]{40}\.png$#')
        ->and(InlineImageSource::pathOf($url))->not->toBeNull();
    Storage::disk('public')->assertExists(InlineImageSource::pathOf($url));

    $audit = Activity::query()->where('description', 'Gambar pengumuman diunggah')->sole();
    expect($audit->causer_id)->toBe($this->operator->id)
        ->and($audit->properties['new']['path'])->toBe(InlineImageSource::pathOf($url));
});

it('unggah gambar ditolak untuk peneliti', function () {
    $researcher = User::factory()->create();
    $researcher->assignRole('researcher');

    uploadImage($researcher, UploadedFile::fake()->image('poster.png'))->assertForbidden();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('unggah SVG ditolak', function () {
    $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    uploadImage($this->operator, $svg)->assertJsonValidationErrors('image');
});

it('unggah WebP ditolak karena GD tidak menjamin penyandiannya', function () {
    // WebP 1×1 lossless mentah: GD di CI tidak bisa membuat WebP palsu.
    $webp = "RIFF\x1a\0\0\0WEBPVP8L\x0d\0\0\0\x2f\0\0\0\x10\x07\x10\x11\x11\x88\x88\xfe\x07\0";

    uploadImage($this->operator, UploadedFile::fake()->createWithContent('poster.webp', $webp))
        ->assertJsonValidationErrors('image');
});

it('unggah berkas teks berekstensi png ditolak', function () {
    $fake = UploadedFile::fake()->createWithContent('poster.png', '<?php echo "bukan gambar";');

    uploadImage($this->operator, $fake)->assertJsonValidationErrors('image');
});

it('unggah gambar terlalu besar dimensinya ditolak', function () {
    uploadImage($this->operator, UploadedFile::fake()->image('lebar.png', 4097, 10))
        ->assertJsonValidationErrors('image');
});

it('metadata EXIF dibuang saat unggah', function () {
    $url = uploadImage($this->operator, jpegWithExif())->assertCreated()->json('url');

    $stored = Storage::disk('public')->get(InlineImageSource::pathOf($url));
    expect($url)->toEndWith('.jpg')
        ->and($stored)->not->toContain('Exif')
        ->and($stored)->not->toContain('GPS-RAHASIA')
        ->and(imagecreatefromstring($stored))->not->toBeFalse();
});
