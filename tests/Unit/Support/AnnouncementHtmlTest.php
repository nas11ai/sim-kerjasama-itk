<?php

// fe-v2-design §4.7 K6/K6a — saringan isi pengumuman dan turunannya yang tanpa query.

use App\Support\Announcement\AnnouncementHtml;
use Tests\TestCase;

uses(TestCase::class);

it('plainText mengubah HTML menjadi teks polos', function () {
    expect(AnnouncementHtml::plainText('<p>A</p><p>B &amp; C</p>'))->toBe('A B & C')
        ->and(AnnouncementHtml::plainText('<p></p><p><br></p>'))->toBe('');
});

it('inlineImagePaths hanya memungut gambar sisipan milik sendiri', function () {
    $own = str_repeat('a', 40);
    $html = '<p><img src="/storage/announcements/inline/'.$own.'.png" alt=""></p>'
        .'<img src="https://evil.example/x.png" alt="">';

    expect(AnnouncementHtml::inlineImagePaths($html))->toBe(['announcements/inline/'.$own.'.png']);
});

it('sanitize membuang tautan berskema terlarang dan mempertahankan mailto', function () {
    $html = AnnouncementHtml::sanitize('<p><a href="mailto:lppm@itk.ac.id">surel</a><a href="ftp://x">f</a></p>');

    expect($html)->toContain('href="mailto:lppm@itk.ac.id"')->not->toContain('ftp:');
});
