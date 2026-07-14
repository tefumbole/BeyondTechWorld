<?php

namespace App\Support;

use App\SiteSetting;

/**
 * Editable front-end content. Values are stored in site_settings under the
 * "content." prefix. The schema() drives the admin editor and the defaults
 * keep the public site unchanged until an admin overrides a field.
 */
class SiteContent
{
    /** Raw stored value for a content key, or the given default. */
    public static function get($key, $default = '')
    {
        $val = SiteSetting::getValue('content.' . $key, null);

        return ($val === null || $val === '') ? $default : $val;
    }

    public static function text($key, $default = '')
    {
        return self::get($key, $default);
    }

    public static function html($key, $default = '')
    {
        return self::get($key, $default);
    }

    /** Resolve an image field to a usable URL, falling back to the default. */
    public static function image($key, $default = '')
    {
        $val = SiteSetting::getValue('content.' . $key, null);
        if (! $val) {
            return $default;
        }
        // Absolute URLs / root-relative paths are returned as-is.
        if (preg_match('#^(https?:)?//#', $val) || strpos($val, '/') === 0) {
            return $val;
        }

        return url('public/' . ltrim($val, '/'));
    }

    /** Persist a scalar content value. */
    public static function put($key, $value)
    {
        SiteSetting::setValue('content.' . $key, $value);
    }

    /**
     * Editable page schema. Each page: label, url, and fields keyed by name.
     * Field: [type, label, default]. type in {text, textarea, html, image}.
     */
    public static function schema()
    {
        return [
            'home' => [
                'label' => 'Home',
                'url' => '/',
                'fields' => [
                    'hero_title'            => ['html', 'Hero title (HTML allowed)', 'Your Technology Bridge to <span class="text-brand-gold">Kigali</span>'],
                    'hero_subtitle'         => ['textarea', 'Hero subtitle', 'Professional IT Consultancy, Enterprise Networking, and Audio-Visual Production, Cloud, AI and Cyber'],
                    'hero_image'            => ['image', 'Hero background image', '/branding/beyond-hero.png'],
                    'cta_primary'           => ['text', 'Hero primary button text', 'Get a Free Quote'],
                    'services_heading'      => ['text', 'Services heading', 'Our Services'],
                    'services_subheading'   => ['text', 'Services subheading', 'Comprehensive technology solutions for your needs'],
                    'why_heading'           => ['text', 'Why-us heading', 'Why Beyond Enterprise?'],
                    'why_subheading'        => ['text', 'Why-us subheading', 'Excellence in every solution we deliver'],
                    'industries_heading'    => ['text', 'Industries heading', 'Industries We Serve'],
                    'industries_subheading' => ['text', 'Industries subheading', 'Trusted by diverse organizations across Africa and the World'],
                    'testimonials_heading'  => ['text', 'Testimonials heading', 'What Our Clients Say'],
                    'testimonials_subheading' => ['text', 'Testimonials subheading', 'Trusted by businesses and organizations across Kigali'],
                    'cta_heading'           => ['text', 'Bottom CTA heading', 'Ready to Get Started?'],
                    'cta_text'              => ['textarea', 'Bottom CTA text', 'Contact us today for a consultation and let us bridge your technology needs.'],
                ],
            ],
            'about' => [
                'label' => 'About',
                'url' => '/about',
                'fields' => [
                    'hero_title'        => ['text', 'Hero title', 'Bridging Technology & Innovation'],
                    'hero_subtitle'     => ['textarea', 'Hero subtitle', 'We are a premier IT consultancy and infrastructure firm dedicated to transforming businesses through cutting-edge technology solutions.'],
                    'mission_heading'   => ['text', 'Mission heading', 'Our Mission'],
                    'mission_text'      => ['textarea', 'Mission text', 'To empower organizations in Africa and beyond with robust, scalable, and secure technology infrastructure. We strive to be the bridge that connects complex technological challenges with simple, effective, and sustainable solutions.'],
                    'about_image'       => ['image', 'Mission image', 'https://horizons-cdn.hostinger.com/81ef3422-3855-479e-bfe8-28a4ceb0df39/513a28b3-47b7-490b-b30a-f9398973361b-a4hCG.png'],
                    'leadership_heading' => ['text', 'Leadership heading', 'Our Leadership'],
                    'leadership_subtext' => ['text', 'Leadership subtext', 'The visionaries driving Beyond Enterprise forward'],
                    'values_heading'    => ['text', 'Core values heading', 'Our Core Values'],
                    'cta_heading'       => ['text', 'CTA heading', 'Ready to work with us?'],
                    'cta_text'          => ['text', 'CTA text', "Let's build something extraordinary together."],
                ],
            ],
            'services' => [
                'label' => 'Services',
                'url' => '/services',
                'fields' => [
                    'hero_title'    => ['html', 'Hero title (HTML allowed)', 'Our <span class="text-brand-gold">Services</span>'],
                    'hero_subtitle' => ['text', 'Hero subtitle', 'Comprehensive technology solutions tailored to your needs'],
                    'heading'       => ['text', 'Section heading', 'Explore Our Expertise'],
                    'subheading'    => ['textarea', 'Section subheading', "From IT infrastructure to cutting-edge AI solutions, we've got you covered."],
                ],
            ],
            'projects' => [
                'label' => 'Projects',
                'url' => '/projects',
                'fields' => [
                    'hero_title'    => ['html', 'Hero title (HTML allowed)', 'Our <span class="text-brand-gold">Projects</span>'],
                    'hero_subtitle' => ['text', 'Hero subtitle', 'See our engineering precision in action'],
                ],
            ],
            'contact' => [
                'label' => 'Contact',
                'url' => '/contact',
                'fields' => [
                    'heading'       => ['text', 'Page heading', 'Get in Touch'],
                    'intro'         => ['textarea', 'Intro text', "Have a question, need assistance, or want to explore partnership opportunities? We're here to help. Reach out to the Beyond Enterprise team today."],
                    'office_name'   => ['text', 'Office name', 'Beyond Enterprise.'],
                    'office_line1'  => ['text', 'Office address line 1', 'Norrsken House Kigali'],
                    'office_line2'  => ['text', 'Office address line 2', 'Kigali, Rwanda'],
                    'person_name'   => ['text', 'Contact person name', 'Nasrah Umwela'],
                    'person_role'   => ['text', 'Contact person role', 'Lead Technical Director'],
                    'phone'         => ['text', 'Phone', '+237 675 321 739'],
                    'email'         => ['text', 'Email', 'info@beyondtechworld.com'],
                    'website'       => ['text', 'Website', 'www.beyondtechworld.com'],
                    'hours_weekday' => ['text', 'Business hours (Mon-Fri)', '9:00 AM - 6:00 PM'],
                    'hours_weekend' => ['text', 'Business hours (Sat-Sun)', 'Closed'],
                ],
            ],
            'gallery' => [
                'label' => 'Gallery',
                'url' => '/gallery',
                'fields' => [
                    'hero_title'    => ['html', 'Hero title (HTML allowed)', 'Our <span class="text-brand-gold">Gallery</span>'],
                    'hero_subtitle' => ['text', 'Hero subtitle', 'Events, projects, and moments from Beyond Enterprise'],
                ],
            ],
        ];
    }

    public static function pageSchema($page)
    {
        $schema = self::schema();

        return $schema[$page] ?? null;
    }

    /** Keys for editable content pages (Home, About, …). */
    public static function contentTabItems()
    {
        $items = [];
        foreach (self::schema() as $key => $page) {
            $items[$key] = $page['label'];
        }

        return $items;
    }

    /** Saved order of content page tabs in Site Content admin. */
    public static function contentTabOrder()
    {
        return SiteMenu::ordered('content_tabs_order', self::contentTabItems());
    }

    /** Page schema keyed by page, sorted for the admin tab bar. */
    public static function orderedSchema()
    {
        $schema = self::schema();
        $ordered = [];
        foreach (self::contentTabOrder() as $key) {
            if (isset($schema[$key])) {
                $ordered[$key] = $schema[$key];
            }
        }
        foreach ($schema as $key => $page) {
            if (! isset($ordered[$key])) {
                $ordered[$key] = $page;
            }
        }

        return $ordered;
    }
}
