<?php

namespace App\Support;

use App\GalleryItem;

/**
 * Media type definitions and embed helpers for the public Gallery page.
 */
class GalleryEmbed
{
    public static function types()
    {
        return [
            'image'         => 'Image',
            'video'         => 'Video file',
            'audio'         => 'Audio file',
            'youtube'       => 'YouTube',
            'youtube_short' => 'YouTube Short',
            'tiktok'        => 'TikTok',
            'instagram'     => 'Instagram',
            'facebook'      => 'Facebook',
        ];
    }

    public static function fileTypes()
    {
        return ['image', 'video', 'audio'];
    }

    public static function urlTypes()
    {
        return ['youtube', 'youtube_short', 'tiktok', 'instagram', 'facebook'];
    }

    public static function youtubeId($url)
    {
        if (! $url) {
            return null;
        }
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/|youtube\.com/shorts/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function tiktokVideoId($url)
    {
        if (! $url) {
            return null;
        }
        if (preg_match('#/video/(\d+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function instagramPath($url)
    {
        if (! $url) {
            return null;
        }
        if (preg_match('#instagram\.com/(p|reel|tv)/([A-Za-z0-9_-]+)#', $url, $m)) {
            return $m[1] . '/' . $m[2];
        }

        return null;
    }

    /** Build view data for a gallery item card. */
    public static function cardData(GalleryItem $item)
    {
        $data = [
            'id'          => $item->id,
            'type'        => $item->type,
            'title'       => $item->title,
            'description' => $item->description,
            'file_url'    => $item->fileUrl(),
            'media_url'   => $item->media_url,
        ];

        switch ($item->type) {
            case 'youtube':
            case 'youtube_short':
                $data['youtube_id'] = self::youtubeId($item->media_url);
                break;
            case 'tiktok':
                $data['tiktok_id'] = self::tiktokVideoId($item->media_url);
                break;
            case 'instagram':
                $data['instagram_path'] = self::instagramPath($item->media_url);
                break;
        }

        return $data;
    }
}
