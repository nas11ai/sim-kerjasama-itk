<?php

namespace App\Support\Announcement;

use HTMLPurifier_Config;
use HTMLPurifier_Context;
use HTMLPurifier_URI;
use HTMLPurifier_URIFilter;

/**
 * fe-v2-design §4.7 K6a — URI tertanam (`img src`) hanya lolos bila menunjuk gambar sisipan
 * milik sendiri. URL luar (piksel pelacak), `data:`, dan path traversal dibuang; `href` tautan
 * tidak disentuh di sini (sudah dibatasi URI.AllowedSchemes).
 */
final class InlineImageUriFilter extends HTMLPurifier_URIFilter
{
    /** @var string */
    public $name = 'AnnouncementInlineImage';

    /**
     * @param  HTMLPurifier_URI  $uri
     * @param  HTMLPurifier_Config  $config
     * @param  HTMLPurifier_Context  $context
     */
    public function filter(&$uri, $config, $context): bool
    {
        if ($context->get('EmbeddedURI', true) !== true) {
            return true;
        }

        return $uri->scheme === null
            && $uri->host === null
            && $uri->query === null
            && $uri->fragment === null
            && is_string($uri->path)
            && InlineImageSource::pathOf($uri->path) !== null;
    }
}
