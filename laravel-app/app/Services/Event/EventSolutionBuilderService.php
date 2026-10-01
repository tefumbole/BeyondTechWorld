<?php

namespace App\Services\Event;

use App\Product;
use App\Services\Rental\RentalAvailabilityService;
use Illuminate\Support\Facades\Schema;

/**
 * Builds event solutions from packages + Product inventory + availability.
 * Conversation stays with OpenAI; business math stays here.
 */
class EventSolutionBuilderService
{
    protected $catalog;
    protected $availability;
    protected $stage;
    protected $screen;
    protected $truss;
    protected $transport;

    public function __construct(
        EventPackageCatalogService $catalog,
        RentalAvailabilityService $availability,
        StagePricingService $stage,
        ScreenPricingService $screen,
        TrussPricingService $truss,
        TransportPricingService $transport
    ) {
        $this->catalog = $catalog;
        $this->availability = $availability;
        $this->stage = $stage;
        $this->screen = $screen;
        $this->truss = $truss;
        $this->transport = $transport;
    }

    public function build(array $requirements)
    {
        $lines = [];
        $commercial = [];
        $warnings = [];
        $pending = [];

        // Map sound experience → DB sound package when not explicitly chosen.
        if (empty($requirements['sound_package']) && ! empty($requirements['sound_mode'])) {
            $requirements['sound_package'] = $this->soundPackageForMode($requirements['sound_mode']);
        }

        $range = null;
        if (! empty($requirements['event_date'])) {
            $range = $this->availability->resolveRange([
                'event_date' => $requirements['event_date'],
                'event_end' => isset($requirements['event_end_date']) ? $requirements['event_end_date'] : null,
            ]);
        }

        $days = $range && ! empty($range['days']) ? (int) $range['days'] : 1;
        if ($days > 1) {
            $pending[] = 'Multi-day pricing needs staff review (base packages are one-day).';
        }

        if (! empty($requirements['sound_package']) && strtoupper($requirements['sound_package']) !== 'NONE') {
            $sound = $this->resolvePackageLine('SOUND', $requirements['sound_package'], $range, $requirements);
            $commercial[] = $sound['commercial'];
            $lines = array_merge($lines, $sound['equipment']);
            $warnings = array_merge($warnings, $sound['warnings']);
        }

        if (! empty($requirements['lighting_package']) && strtoupper($requirements['lighting_package']) !== 'NONE') {
            $light = $this->resolvePackageLine('LIGHTING', $requirements['lighting_package'], $range, $requirements);
            $commercial[] = $light['commercial'];
            $lines = array_merge($lines, $light['equipment']);
            $warnings = array_merge($warnings, $light['warnings']);
        }

        if (! empty($requirements['want_screen']) || (! empty($requirements['screen_length_m']) && ! empty($requirements['screen_width_m']))) {
            $screen = $this->resolveScreen($range, $requirements);
            if (! empty($screen['pending_pricing'])) {
                $pending[] = $screen['message'];
            }
            $commercial[] = $screen['commercial'];
            $lines = array_merge($lines, $screen['equipment']);
        }

        if (! empty($requirements['stage_length_m']) && ! empty($requirements['stage_width_m'])) {
            $stage = $this->stage->calculate($requirements['stage_length_m'], $requirements['stage_width_m']);
            $commercial[] = [
                'key' => 'STAGE',
                'label' => 'Stage '.$stage['length_m'].'m × '.$stage['width_m'].'m ('.$stage['area_m2'].' m²)',
                'price' => $stage['price'],
                'currency' => 'XAF',
                'pending_pricing' => false,
            ];
        } elseif (! empty($requirements['want_stage'])) {
            $pending[] = 'Stage size needed before pricing (e.g. 4m × 4m).';
            $commercial[] = [
                'key' => 'STAGE',
                'label' => 'Stage',
                'price' => null,
                'pending_pricing' => true,
            ];
        }

        if (! empty($requirements['truss_package']) && strtoupper($requirements['truss_package']) !== 'NONE') {
            $truss = $this->truss->priceFor($requirements['truss_package']);
            if (! empty($truss['success'])) {
                $commercial[] = [
                    'key' => 'TRUSS',
                    'label' => $truss['name'],
                    'price' => $truss['price'],
                    'currency' => 'XAF',
                    'pending_pricing' => false,
                ];
            }
        }

        $transport = $this->transport->calculate([
            'within_town' => ! empty($requirements['within_town']),
            'location_scope' => isset($requirements['location_scope']) ? $requirements['location_scope'] : null,
            'sound_package' => isset($requirements['sound_package']) ? $requirements['sound_package'] : null,
            'lighting_package' => isset($requirements['lighting_package']) ? $requirements['lighting_package'] : null,
            'transport_tier' => isset($requirements['transport_tier']) ? $requirements['transport_tier'] : null,
        ]);
        $commercial[] = [
            'key' => 'TRANSPORT',
            'label' => 'Transportation',
            'price' => $transport['price'],
            'currency' => 'XAF',
            'pending_pricing' => ! empty($transport['pending_pricing']),
            'status' => isset($transport['status']) ? $transport['status'] : null,
            'message' => isset($transport['message']) ? $transport['message'] : null,
        ];
        if (! empty($transport['pending_pricing'])) {
            $pending[] = $transport['message'];
        }

        $total = 0.0;
        $hasPending = false;
        foreach ($commercial as $row) {
            if (! empty($row['pending_pricing']) || $row['price'] === null) {
                $hasPending = true;
                continue;
            }
            $total += (float) $row['price'];
        }

        $equipmentOk = true;
        foreach ($lines as $line) {
            if (empty($line['available'])) {
                $equipmentOk = false;
                break;
            }
        }

        return [
            'success' => true,
            'path' => 'EVENT_SOLUTION',
            'requirements' => $requirements,
            'event' => [
                'type' => isset($requirements['event_type']) ? $requirements['event_type'] : null,
                'date' => isset($requirements['event_date']) ? $requirements['event_date'] : null,
                'end_date' => isset($requirements['event_end_date']) ? $requirements['event_end_date'] : null,
                'days' => $days,
                'venue' => isset($requirements['venue']) ? $requirements['venue'] : (isset($requirements['location']) ? $requirements['location'] : null),
                'guests' => isset($requirements['guest_count']) ? $requirements['guest_count'] : null,
                'sound_mode' => isset($requirements['sound_mode']) ? $requirements['sound_mode'] : null,
            ],
            'commercial_lines' => $commercial,
            'equipment_lines' => $lines,
            'equipment_available' => $equipmentOk,
            'estimated_total' => $hasPending ? null : $total,
            'estimated_total_formatted' => $hasPending ? 'Pending Pricing' : number_format($total, 0).' CFA',
            'pending_pricing' => $pending,
            'warnings' => $warnings,
            'summary_text' => $this->summaryText($requirements, $commercial, $total, $hasPending, $warnings),
            // Multiple events per day are allowed — availability is qty-based, not calendar-exclusive.
            'multiple_events_per_day_supported' => true,
        ];
    }

    public function searchSuitableProducts($categoryHint, array $slots = [], $limit = 6)
    {
        if (! Schema::hasTable('products')) {
            return ['success' => false, 'products' => [], 'error' => 'catalogue_unavailable'];
        }
        $hint = strtolower(trim((string) $categoryHint));
        $map = [
            'speaker' => ['speaker', 'line array', 'point source'],
            'speakers' => ['speaker', 'line array'],
            'sound' => ['speaker', 'subwoofer', 'mixer', 'microphone'],
            'subwoofer' => ['subwoofer', 'sub'],
            'mixer' => ['mixer'],
            'microphone' => ['microphone', 'mic'],
            'light' => ['par light', 'wash', 'robot'],
            'lighting' => ['par light', 'wash', 'robot'],
            'screen' => ['led screen', 'led', 'screen'],
            'stage' => ['stage'],
            'truss' => ['truss'],
        ];
        $terms = isset($map[$hint]) ? $map[$hint] : [$hint];
        $query = Product::query()->where('is_active', true)->where(function ($inner) use ($terms) {
            foreach ($terms as $term) {
                $inner->orWhere('name', 'like', '%'.$term.'%')->orWhere('code', 'like', '%'.$term.'%');
            }
        })->orderBy('name')->limit(max(3, (int) $limit));

        $range = null;
        if (! empty($slots['event_date'])) {
            $range = $this->availability->resolveRange($slots);
        }

        $out = [];
        foreach ($query->get() as $product) {
            $row = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'qty_on_hand' => Schema::hasColumn('products', 'qty') ? (float) $product->qty : null,
                'listed_day_rate' => Schema::hasColumn('products', 'rent_price_per_day') ? (float) $product->rent_price_per_day : null,
                'image' => ! empty($product->image) ? $product->image : null,
            ];
            if ($range && ! empty($range['start'])) {
                $check = $this->availability->assess($product, 1, $range['start'], $range['end']);
                $row['available'] = ! empty($check['available']);
                $row['available_qty'] = isset($check['available_qty']) ? $check['available_qty'] : null;
            }
            $out[] = $row;
        }

        return [
            'success' => true,
            'query' => $categoryHint,
            'products' => $out,
            'note' => 'These are catalogue candidates for an event solution — not a single forced SKU. Prefer packages when the customer asked for event sound.',
            'ui' => [
                'type' => 'PRODUCT_CARD_LIST',
                'products' => array_map(function ($p) {
                    return [
                        'value' => 'product:'.$p['id'],
                        'label' => ($p['image'] ? '' : '🔊 ').$p['name'],
                        'description' => isset($p['available_qty'])
                            ? ('Available qty: '.$p['available_qty'])
                            : 'Catalogue item',
                        'image' => $p['image'],
                    ];
                }, array_slice($out, 0, 6)),
            ],
        ];
    }

    protected function resolvePackageLine($category, $code, $range, array $requirements)
    {
        $pkg = $this->catalog->find($category, $code);
        $warnings = [];
        $equipment = [];
        $commercial = [
            'key' => $category,
            'code' => strtoupper((string) $code),
            'label' => $pkg ? $pkg->name : ($category.' '.$code),
            'price' => $pkg ? (float) $pkg->base_price : null,
            'currency' => 'XAF',
            'pending_pricing' => ! $pkg,
        ];
        if (! $pkg) {
            $warnings[] = $category.' package '.$code.' is not configured.';

            return compact('commercial', 'equipment', 'warnings');
        }
        $pkg->load('components');
        foreach ($pkg->components as $component) {
            $product = null;
            if ($component->product_id) {
                $product = Product::find($component->product_id);
            }
            if (! $product && $component->search_query) {
                $product = $this->availability->search($component->search_query, 5)->first(function ($p) use ($range, $component) {
                    if (! $range) {
                        return true;
                    }
                    $check = $this->availability->assess($p, (int) $component->qty, $range['start'], $range['end']);

                    return ! empty($check['available']);
                });
                if (! $product) {
                    $product = $this->availability->search($component->search_query, 1)->first();
                }
            }
            if (! $product) {
                $warnings[] = 'No catalogue match for '.$component->category_key.' ('.$component->search_query.').';
                $equipment[] = [
                    'category_key' => $component->category_key,
                    'requested_qty' => (int) $component->qty,
                    'available' => false,
                    'product_id' => null,
                    'name' => null,
                ];
                continue;
            }
            $qty = max(1, (int) $component->qty);
            $available = true;
            $availableQty = Schema::hasColumn('products', 'qty') ? (float) $product->qty : null;
            if ($range && ! empty($range['start'])) {
                $check = $this->availability->assess($product, $qty, $range['start'], $range['end']);
                $available = ! empty($check['available']);
                $availableQty = isset($check['available_qty']) ? $check['available_qty'] : $availableQty;
                if (! $available) {
                    $alts = $this->availability->alternatives($product, $qty, $range['start'], $range['end'], 3);
                    if ($alts) {
                        $alt = $alts[0];
                        $product = Product::find($alt['id']);
                        if ($product) {
                            $check = $this->availability->assess($product, $qty, $range['start'], $range['end']);
                            $available = ! empty($check['available']);
                            $availableQty = isset($check['available_qty']) ? $check['available_qty'] : null;
                            $warnings[] = 'Switched '.$component->category_key.' to available alternative '.$product->name.'.';
                        }
                    }
                }
            }
            $equipment[] = [
                'category_key' => $component->category_key,
                'product_id' => $product->id,
                'name' => $product->name,
                'requested_qty' => $qty,
                'available' => $available,
                'available_qty' => $availableQty,
            ];
            if (! $available) {
                $warnings[] = $product->name.' cannot cover qty '.$qty.' on the requested date; package may need staff adjustment.';
            }
        }

        return compact('commercial', 'equipment', 'warnings');
    }

    protected function soundPackageForMode($mode)
    {
        $mode = strtoupper(trim((string) $mode));
        $map = [
            'PLAYBACK' => 'BASIC',
            'PLAYBACK_PIANO' => 'STANDARD',
            'PIANO' => 'STANDARD',
            'PIANO_BAR' => 'STANDARD',
            'FULL_LIVE' => 'PREMIUM',
            'FULL_SETUP' => 'PREMIUM',
        ];

        return isset($map[$mode]) ? $map[$mode] : 'BASIC';
    }

    protected function resolveScreen($range, array $requirements = [])
    {
        $length = isset($requirements['screen_length_m']) ? $requirements['screen_length_m'] : null;
        $width = isset($requirements['screen_width_m']) ? $requirements['screen_width_m'] : null;
        $priced = null;
        if ($length && $width) {
            $priced = $this->screen->calculate($length, $width);
        }

        $product = $this->availability->search('led screen', 3)->first();
        if (! $product) {
            $product = $this->availability->search('led', 3)->first();
        }

        $equipment = [];
        if ($product) {
            $available = true;
            $availableQty = null;
            if ($range && ! empty($range['start'])) {
                $check = $this->availability->assess($product, 1, $range['start'], $range['end']);
                $available = ! empty($check['available']);
                $availableQty = isset($check['available_qty']) ? $check['available_qty'] : null;
            }
            $equipment[] = [
                'category_key' => 'LED_SCREEN',
                'product_id' => $product->id,
                'name' => $product->name,
                'requested_qty' => 1,
                'available' => $available,
                'available_qty' => $availableQty,
            ];
        }

        if ($priced) {
            return [
                'commercial' => [
                    'key' => 'SCREEN',
                    'label' => 'LED Screen '.$priced['length_m'].'m × '.$priced['width_m'].'m ('.$priced['area_m2'].' m²)',
                    'price' => $priced['price'],
                    'currency' => 'XAF',
                    'pending_pricing' => false,
                ],
                'equipment' => $equipment,
                'pending_pricing' => false,
                'message' => $priced['message'],
            ];
        }

        return [
            'commercial' => [
                'key' => 'SCREEN',
                'label' => 'LED Screen',
                'price' => null,
                'pending_pricing' => true,
            ],
            'equipment' => $equipment,
            'pending_pricing' => true,
            'message' => app(\App\Services\Event\ScreenPricingService::class)->sizePrompt(),
        ];
    }

    protected function summaryText(array $req, array $commercial, $total, $hasPending, array $warnings)
    {
        $bits = ["Here's what I've prepared for your event:"];
        if (! empty($req['event_type'])) {
            $bits[] = ucfirst((string) $req['event_type']);
        }
        if (! empty($req['event_date'])) {
            $bits[] = '📅 '.$req['event_date'];
        }
        $venue = isset($req['venue']) ? $req['venue'] : (isset($req['location']) ? $req['location'] : null);
        if ($venue) {
            $bits[] = '📍 '.$venue;
        }
        if (! empty($req['guest_count'])) {
            $bits[] = 'Guests: '.$req['guest_count'];
        }
        $bits[] = '';
        foreach ($commercial as $row) {
            $price = ! empty($row['pending_pricing']) || $row['price'] === null
                ? 'Pending Pricing'
                : number_format((float) $row['price'], 0).' CFA';
            $bits[] = ($row['label'] ?? $row['key']).' — '.$price;
        }
        $bits[] = '';
        $bits[] = 'Estimated Total: '.($hasPending ? 'Pending Pricing' : number_format((float) $total, 0).' CFA');
        if ($warnings) {
            $bits[] = '';
            $bits[] = 'Notes: '.implode(' ', array_slice($warnings, 0, 3));
        }
        $bits[] = '';
        $bits[] = 'Would you like me to prepare the formal quotation for your review?';

        return implode("\n", $bits);
    }
}
