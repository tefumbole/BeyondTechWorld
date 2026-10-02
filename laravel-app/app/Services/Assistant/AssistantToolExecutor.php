<?php

namespace App\Services\Assistant;

use App\Assistant\AssistantKnowledge;
use App\Booking;
use App\Product;
use App\Quotation;
use App\Sale;
use App\Services\Rental\RentalAvailabilityService;
use App\Services\Rental\RentalQuoteService;
use App\WhatsApp\Lead;
use App\WhatsApp\WhatsAppContact;
use Illuminate\Support\Facades\Schema;

class AssistantToolExecutor
{
    protected $registry;
    protected $policy;
    protected $handover;

    public function __construct(
        AssistantToolRegistry $registry,
        AssistantPolicyService $policy,
        AssistantHandoverService $handover
    ) {
        $this->registry = $registry;
        $this->policy = $policy;
        $this->handover = $handover;
    }

    public function execute($name, array $params, array $context)
    {
        if (preg_match('/grade|pass_intern|release_next|official_score/i', (string) $name)) {
            return ['success' => false, 'error' => 'grading_forbidden'];
        }
        if (preg_match('/approve_overtime|approve_attendance|override_location|modify_historical|bypass_ownership|another_user_document|bulk_documents|adjust_rent|waive_rent|terminate_tenancy|mark_rent_paid|override_property_ownership|reconcile_override|initiate_debit/i', (string) $name)) {
            return ['success' => false, 'error' => 'privileged'];
        }
        if (isset($params['cloud_tenant_id']) || isset($params['tenant_id'])) {
            unset($params['cloud_tenant_id'], $params['tenant_id']);
        }
        if ($this->tenantScopeFor($name) === 'TENANT' && \Illuminate\Support\Facades\Schema::hasColumn('products', 'cloud_tenant_id')) {
            if (! app(\App\Services\Cloud\CloudTenantContext::class)->has()) {
                return ['success' => false, 'error' => 'tenant_context_required'];
            }
        }
        $moduleBlock = $this->moduleBlock($name);
        if ($moduleBlock) {
            return $moduleBlock;
        }
        $ownerTools = [
            'get_ai_status', 'set_ai_enabled', 'set_ai_first', 'switch_eligible_conversations_to_ai',
            'get_conversations_needing_attention', 'get_open_leads_summary', 'get_pending_quotation_summary',
            'get_failed_whatsapp_summary', 'assign_conversation_to_me', 'return_conversation_to_ai',
        ];
        if (in_array($name, $ownerTools, true)) {
            return app(\App\Services\WhatsApp\OwnerCommandService::class)->run($name, $params, $context);
        }
        $appointmentTools = [
            'check_appointment_availability', 'create_appointment', 'get_my_appointments',
            'cancel_appointment', 'reschedule_appointment',
        ];
        if (in_array($name, $appointmentTools, true)) {
            return app(\App\Services\Appointment\AppointmentService::class)->tool($name, $params, $context);
        }
        $meta = $this->registry->get($name);
        if (! $meta) {
            return ['success' => false, 'error' => 'unknown_tool'];
        }
        $roles = isset($context['roles']) ? $context['roles'] : [];
        list($ok, $reason) = $this->policy->toolAllowed($name, $meta, $roles);
        if (! $ok) {
            return ['success' => false, 'error' => $reason, 'tool' => $name];
        }
        $method = 'tool'.str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        if (! method_exists($this, $method)) {
            return ['success' => false, 'error' => 'unimplemented_tool'];
        }

        return $this->{$method}($params, $context);
    }

    /**
     * PLATFORM tools are installation commands.
     * IDENTITY tools follow a person (property occupancy is not a CloudTenant).
     * PUBLIC tools do not read another company's records.
     * TENANT tools read only the active company, even if the prompt says otherwise.
     */
    protected function tenantScopeFor($name)
    {
        $identity = [
            'get_my_tenancy', 'get_rent_balance', 'get_rent_due_date', 'get_rent_payment_history',
            'get_maintenance_requests', 'create_maintenance_request', 'get_maintenance_status',
            'add_maintenance_attachment', 'request_tenant_document', 'reject_payment_claim',
            'clarify_tenant_balance',
        ];
        $public = [
            'get_company_information', 'search_company_knowledge', 'get_services',
            'get_sound_experience_options', 'get_event_extras_options', 'get_sound_packages',
            'get_lighting_packages', 'calculate_stage_price', 'calculate_screen_price',
            'get_truss_options', 'calculate_transport_price', 'check_appointment_availability',
        ];
        if (in_array($name, $identity, true)) {
            return 'IDENTITY';
        }
        if (in_array($name, $public, true)) {
            return 'PUBLIC';
        }

        return 'TENANT';
    }

    /**
     * A CUSTOMER company cannot reach a module through MAI when the subscription
     * does not allow it. The tool is also omitted from the prompt by the registry.
     */
    protected function moduleBlock($name)
    {
        $context = app(\App\Services\Cloud\CloudTenantContext::class);
        $tenant = $context->tenant();
        if (! $tenant || $tenant->type !== \App\Cloud\CloudTenantType::CUSTOMER) {
            return null;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('cloud_subscriptions')) {
            return null;
        }
        $access = app(\App\Services\Cloud\CloudModuleAccessService::class);
        $capability = $access->toolCapability($name);
        if (! $capability) {
            return null;
        }
        $meta = $this->registry->get($name);
        $write = $meta && ! empty($meta['write']);
        $allowed = $write
            ? $access->canWriteCapability($tenant, $capability)
            : $access->canReadCapability($tenant, $capability);
        if ($allowed) {
            return null;
        }

        return ['success' => false, 'error' => 'module_not_entitled', 'tool' => $name];
    }

    protected function toolGetContactSummary(array $params, array $context)
    {
        return [
            'success' => true,
            'name' => isset($context['contact_name']) ? $context['contact_name'] : null,
            'phone' => isset($context['phone']) ? $context['phone'] : null,
            'roles' => isset($context['roles']) ? $context['roles'] : [],
        ];
    }

    protected function toolGetCompanyInformation(array $params, array $context)
    {
        return ['success' => true, 'entries' => $this->knowledge(['company', 'hours', 'support'])];
    }

    protected function toolSearchCompanyKnowledge(array $params, array $context)
    {
        $q = trim((string) (isset($params['query']) ? $params['query'] : ''));
        if (! Schema::hasTable('assistant_knowledge')) {
            return ['success' => true, 'entries' => [], 'note' => 'Knowledge base unavailable.'];
        }
        $query = AssistantKnowledge::where('enabled', true);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)
                    ->orWhere('content', 'like', $like)
                    ->orWhere('category', 'like', $like);
            });
        }
        $rows = $query->orderBy('id')->limit(8)->get();
        $entries = [];
        foreach ($rows as $row) {
            $entries[] = [
                'title' => $row->title,
                'category' => $row->category,
                'content' => mb_substr((string) $row->content, 0, 800),
            ];
        }

        return ['success' => true, 'entries' => $entries, 'query' => $q];
    }

    protected function toolGetServices(array $params, array $context)
    {
        return ['success' => true, 'entries' => $this->knowledge(['services', 'rental', 'internship'])];
    }

    protected function toolSearchRentalProducts(array $params, array $context)
    {
        if (! Schema::hasTable('products')) {
            return ['success' => true, 'products' => [], 'note' => 'Catalogue unavailable.'];
        }
        $q = trim((string) (isset($params['query']) ? $params['query'] : ''));
        $query = Product::query()->where('is_active', true);
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%');
            });
        }
        $rows = $query->orderBy('name')->limit(8)->get(['id', 'name', 'code', 'rent_price_per_day', 'rent_price_per_hour']);
        $products = [];
        foreach ($rows as $row) {
            $products[] = [
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'listed_day_rate' => $row->rent_price_per_day,
                'listed_hour_rate' => $row->rent_price_per_hour,
                'availability_checked' => false,
            ];
        }

        return ['success' => true, 'products' => $products, 'availability_checked' => false];
    }

    protected function toolCheckRentalAvailability(array $params, array $context)
    {
        $availability = app(RentalAvailabilityService::class);
        $range = $availability->resolveRange($params);
        $query = isset($params['product']) ? $params['product'] : (isset($params['query']) ? $params['query'] : '');
        $qNorm = strtolower(trim((string) $query));
        $vague = in_array($qNorm, ['', 'speaker', 'speakers', 'sound', 'audio', 'lighting', 'light', 'lights', 'mic', 'microphone', 'mixer'], true)
            || preg_match('/^(some|any)?\s*speakers?$/i', $qNorm);
        if ($vague) {
            return [
                'success' => true,
                'event_first' => true,
                'redirect' => 'build_event_solution',
                'availability_checked' => false,
                'message' => 'That sounds like an event sound/lighting request, not a single catalogue SKU. Collect event date/venue/guests and use build_event_solution / get_sound_experience_options instead of checking one random product.',
                'ui' => app(\App\Services\Event\EventPackageCatalogService::class)->soundModeGroup(),
            ];
        }
        if (! $range) {
            return $this->toolSearchRentalProducts($params, $context);
        }
        $products = $availability->search($query, 5);
        $product = $products->first();
        if (! $product) {
            $proposal = $this->proposalAvailability($params, $context, $range);
            if ($proposal) {
                return $proposal;
            }

            return ['success' => true, 'products' => [], 'availability_checked' => true, 'available' => false, 'reason' => 'not_found'];
        }
        $qty = isset($params['qty']) ? (int) $params['qty'] : 1;
        $chosen = null;
        $check = null;
        foreach ($products as $candidate) {
            $assessed = $availability->assess($candidate, $qty, $range['start'], $range['end']);
            if (! empty($assessed['available'])) {
                $chosen = $candidate;
                $check = $assessed;
                break;
            }
            if ($check === null) {
                $chosen = $candidate;
                $check = $assessed;
            }
        }
        $check['days'] = $range['days'];
        $check['products'] = [$check];
        if (empty($check['available'])) {
            $check['alternatives'] = $availability->alternatives($chosen, $qty, $range['start'], $range['end']);
            $check['message'] = 'That specific item is tight on '.$range['start'].'. Here are suitable alternatives from inventory — or we can build a full event sound package instead.';
        }
        if (! empty($check['priced']) && ! empty($check['available'])) {
            $check['estimate_total'] = round($check['day_rate'] * $check['requested_qty'] * $range['days'], 2);
        }

        return $check;
    }

    protected function toolGetSoundExperienceOptions(array $params, array $context)
    {
        $ui = app(\App\Services\Event\EventOptionPresentation::class)->soundUi();

        return [
            'success' => true,
            'options' => $ui['options'],
            'ui' => $ui,
            'message' => $ui['prompt'],
            'next_step' => 'After sound mode is chosen, call get_event_extras_options for Lights/Screens/Stage checkboxes.',
        ];
    }

    protected function toolGetEventExtrasOptions(array $params, array $context)
    {
        $ui = app(\App\Services\Event\EventOptionPresentation::class)->extrasUi();

        return [
            'success' => true,
            'options' => $ui['options'],
            'ui' => $ui,
            'message' => $ui['prompt'],
            'next_step' => 'If lights selected → get_lighting_packages. If screens selected → ask Height×Width in meters OR total m² (no yes/no buttons), then calculate_screen_price. If stage → ask size then calculate_stage_price.',
        ];
    }

    protected function toolGetSoundPackages(array $params, array $context)
    {
        $catalog = app(\App\Services\Event\EventPackageCatalogService::class);
        $ui = $catalog->optionGroup('SOUND', 'Which sound package would you like to explore?');

        return ['success' => true, 'packages' => $catalog->packages('SOUND'), 'ui' => $ui];
    }

    protected function toolGetLightingPackages(array $params, array $context)
    {
        $catalog = app(\App\Services\Event\EventPackageCatalogService::class);
        $ui = app(\App\Services\Event\EventOptionPresentation::class)->lightingUi();

        return [
            'success' => true,
            'packages' => $catalog->packages('LIGHTING'),
            'ui' => $ui,
            'message' => $ui['prompt'],
        ];
    }

    protected function toolCalculateScreenPrice(array $params, array $context)
    {
        $pricing = app(\App\Services\Event\ScreenPricingService::class);
        $length = isset($params['length_m']) ? $params['length_m'] : (isset($params['screen_length_m']) ? $params['screen_length_m'] : (isset($params['height_m']) ? $params['height_m'] : null));
        $width = isset($params['width_m']) ? $params['width_m'] : (isset($params['screen_width_m']) ? $params['screen_width_m'] : null);
        $area = isset($params['area_m2']) ? $params['area_m2'] : (isset($params['square_meters']) ? $params['square_meters'] : null);
        if (($length === null || $width === null) && $area === null && ! empty($params['text'])) {
            $parsed = $pricing->parseSize($params['text']);
            if (is_array($parsed) && isset($parsed['area'])) {
                $area = $parsed['area'];
            } elseif (is_array($parsed) && isset($parsed[0], $parsed[1])) {
                $length = $parsed[0];
                $width = $parsed[1];
            }
        }
        if ($area !== null && $area !== '' && ($length === null || $width === null)) {
            return $pricing->calculateArea($area);
        }
        if ($length === null || $width === null) {
            return [
                'success' => false,
                'error' => 'need_dimensions',
                'message' => $pricing->sizePrompt(),
                'ui' => null,
                'choices' => [],
            ];
        }

        return $pricing->calculate($length, $width);
    }

    protected function toolGetPackageDetails(array $params, array $context)
    {
        $category = isset($params['category']) ? $params['category'] : '';
        $code = isset($params['code']) ? $params['code'] : '';
        $catalog = app(\App\Services\Event\EventPackageCatalogService::class);
        $pkg = $catalog->find($category, $code);
        if (! $pkg) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $pkg->load('components');

        return ['success' => true, 'package' => $catalog->serialize($pkg)];
    }

    protected function toolSearchEventProducts(array $params, array $context)
    {
        $category = isset($params['category']) ? $params['category'] : (isset($params['query']) ? $params['query'] : 'speaker');

        return app(\App\Services\Event\EventSolutionBuilderService::class)->searchSuitableProducts($category, $params, 8);
    }

    protected function toolCheckEventEquipmentAvailability(array $params, array $context)
    {
        $solution = app(\App\Services\Event\EventSolutionBuilderService::class)->build($this->eventRequirementsFromParams($params));

        return [
            'success' => true,
            'equipment_available' => ! empty($solution['equipment_available']),
            'equipment_lines' => $solution['equipment_lines'],
            'warnings' => $solution['warnings'],
            'multiple_events_per_day_supported' => true,
            'message' => ! empty($solution['equipment_available'])
                ? 'Equipment can be allocated for this date based on remaining inventory (other events that day do not block the company).'
                : 'Some package lines need alternatives or staff review for this date.',
        ];
    }

    protected function toolCalculateStagePrice(array $params, array $context)
    {
        $length = isset($params['length_m']) ? $params['length_m'] : null;
        $width = isset($params['width_m']) ? $params['width_m'] : null;
        if (($length === null || $width === null) && ! empty($params['text'])) {
            $parsed = app(\App\Services\Event\StagePricingService::class)->parseDimensions($params['text']);
            if ($parsed) {
                $length = $parsed[0];
                $width = $parsed[1];
            }
        }
        if ($length === null || $width === null) {
            return ['success' => false, 'error' => 'need_dimensions', 'message' => 'What stage size would you like? For example, 4m × 4m.'];
        }

        return app(\App\Services\Event\StagePricingService::class)->calculate($length, $width);
    }

    protected function toolGetTrussOptions(array $params, array $context)
    {
        $options = app(\App\Services\Event\TrussPricingService::class)->options();
        $ui = [
            'type' => 'OPTION_GROUP',
            'category' => 'TRUSS',
            'prompt' => 'Would you also need truss?',
            'options' => array_map(function ($o) {
                $label = (isset($o['icon']) ? $o['icon'] : '').' '.$o['name'];
                if ((float) $o['price'] > 0) {
                    $label .= ' — '.number_format((float) $o['price'], 0).' CFA';
                }

                return ['value' => 'truss:'.strtolower($o['code']), 'label' => trim($label), 'code' => $o['code'], 'price' => $o['price']];
            }, $options),
        ];

        return ['success' => true, 'options' => $options, 'ui' => $ui];
    }

    protected function toolCalculateTransportPrice(array $params, array $context)
    {
        $within = ! empty($params['within_town']) && ! in_array(strtolower((string) $params['within_town']), ['0', 'false', 'no'], true);

        return app(\App\Services\Event\TransportPricingService::class)->calculate([
            'within_town' => $within,
            'sound_package' => isset($params['sound_package']) ? $params['sound_package'] : null,
            'lighting_package' => isset($params['lighting_package']) ? $params['lighting_package'] : null,
            'transport_tier' => isset($params['transport_tier']) ? $params['transport_tier'] : null,
            'location_scope' => isset($params['location_scope']) ? $params['location_scope'] : null,
        ]);
    }

    protected function toolBuildEventSolution(array $params, array $context)
    {
        $solution = app(\App\Services\Event\EventSolutionBuilderService::class)->build($this->eventRequirementsFromParams($params));
        $solution['ui'] = [
            'type' => 'QUOTATION_SUMMARY',
            'summary' => $solution['summary_text'],
            'options' => [
                ['value' => 'quotation:prepare', 'label' => '✅ Prepare formal quotation'],
                ['value' => 'quotation:revise', 'label' => '✏️ Change something'],
            ],
        ];

        return $solution;
    }

    protected function toolCalculateEventEstimate(array $params, array $context)
    {
        return $this->toolBuildEventSolution($params, $context);
    }

    protected function toolCreateEventQuotationDraft(array $params, array $context)
    {
        $solution = app(\App\Services\Event\EventSolutionBuilderService::class)->build($this->eventRequirementsFromParams($params));

        return app(\App\Services\Event\EventQuotationService::class)->createDraftFromSolution($solution, $params, $context);
    }

    protected function toolGenerateQuotationReview(array $params, array $context)
    {
        $id = isset($params['quotation_id']) ? (int) $params['quotation_id'] : 0;

        return app(\App\Services\Event\EventQuotationService::class)->generateCustomerReview($id, $context);
    }

    protected function toolGetQuotationReviewStatus(array $params, array $context)
    {
        $id = isset($params['quotation_id']) ? (int) $params['quotation_id'] : 0;

        return app(\App\Services\Event\EventQuotationService::class)->reviewStatus($id);
    }

    protected function eventRequirementsFromParams(array $params)
    {
        $bool = function ($v) {
            if (is_bool($v)) {
                return $v;
            }
            $s = strtolower(trim((string) $v));

            return in_array($s, ['1', 'true', 'yes', 'y'], true);
        };

        return array_filter([
            'event_type' => isset($params['event_type']) ? $params['event_type'] : null,
            'event_date' => isset($params['event_date']) ? $params['event_date'] : null,
            'event_end_date' => isset($params['event_end_date']) ? $params['event_end_date'] : (isset($params['event_end']) ? $params['event_end'] : null),
            'venue' => isset($params['venue']) ? $params['venue'] : (isset($params['location']) ? $params['location'] : null),
            'location' => isset($params['location']) ? $params['location'] : null,
            'guest_count' => isset($params['guest_count']) ? $params['guest_count'] : (isset($params['guests']) ? $params['guests'] : null),
            'sound_mode' => isset($params['sound_mode']) ? $params['sound_mode'] : null,
            'sound_package' => isset($params['sound_package']) ? strtoupper((string) $params['sound_package']) : null,
            'lighting_package' => isset($params['lighting_package']) ? strtoupper((string) $params['lighting_package']) : null,
            'want_screen' => isset($params['want_screen']) ? $bool($params['want_screen']) : null,
            'want_stage' => isset($params['want_stage']) ? $bool($params['want_stage']) : null,
            'stage_length_m' => isset($params['stage_length_m']) ? $params['stage_length_m'] : null,
            'stage_width_m' => isset($params['stage_width_m']) ? $params['stage_width_m'] : null,
            'screen_length_m' => isset($params['screen_length_m']) ? $params['screen_length_m'] : null,
            'screen_width_m' => isset($params['screen_width_m']) ? $params['screen_width_m'] : null,
            'truss_package' => isset($params['truss_package']) ? strtoupper((string) $params['truss_package']) : null,
            'within_town' => isset($params['within_town']) ? $bool($params['within_town']) : true,
            'location_scope' => isset($params['location_scope']) ? $params['location_scope'] : null,
            'transport_tier' => isset($params['transport_tier']) ? $params['transport_tier'] : null,
        ], function ($v) {
            return $v !== null && $v !== '';
        });
    }

    protected function proposalAvailability(array $params, array $context, array $range)
    {
        $conversation = isset($context['conversation']) ? $context['conversation'] : null;
        if (! $conversation) {
            return null;
        }
        $request = app(\App\Services\Rental\RentalRequestService::class)->active($conversation);
        if (! $request) {
            return null;
        }
        $proposal = app(\App\Services\Rental\RentalRecommendationService::class)->propose($request, $params);
        foreach (isset($proposal['lines']) ? $proposal['lines'] : [] as $line) {
            if (empty($line['success']) || empty($line['available']) || empty($line['name'])) {
                continue;
            }

            return [
                'success' => true,
                'availability_checked' => true,
                'available' => true,
                'priced' => true,
                'name' => $line['name'],
                'start' => $range['start'],
                'available_qty' => isset($line['available_qty']) ? $line['available_qty'] : $line['quantity'],
                'day_rate' => isset($line['unit_price']) ? $line['unit_price'] : 0,
                'requested_qty' => isset($line['quantity']) ? $line['quantity'] : 1,
                'products' => [$line],
            ];
        }

        return null;
    }

    protected function toolCreateRentalQuotation(array $params, array $context)
    {
        return app(RentalQuoteService::class)->createDraft($params, $context);
    }

    protected function toolRequestRentalBooking(array $params, array $context)
    {
        return app(RentalQuoteService::class)->requestBooking($params, $context);
    }

    protected function toolGetRentalProductInformation(array $params, array $context)
    {
        $id = isset($params['product_id']) ? (int) $params['product_id'] : 0;
        $product = $id && Schema::hasTable('products') ? Product::where('is_active', true)->find($id) : null;
        if (! $product) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return [
            'success' => true,
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'listed_day_rate' => $product->rent_price_per_day,
            'availability_checked' => false,
        ];
    }

    protected function toolGetCustomerQuotations(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        if (! $customerId || ! Schema::hasTable('quotations')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $rows = Quotation::where('customer_id', $customerId)->orderByDesc('id')->limit(5)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row->id,
                'reference' => $row->reference_no,
                'status' => Quotation::statusLabel($row->quotation_status),
            ];
        }

        return ['success' => true, 'quotations' => $out];
    }

    protected function toolGetCustomerQuotationDetails(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        if (! $customerId || ! Schema::hasTable('quotations')) {
            return ['success' => false, 'error' => 'not_found', 'message' => "I couldn't find a previous quotation on this account."];
        }
        $id = isset($params['quotation_id']) ? (int) $params['quotation_id'] : 0;
        $query = Quotation::where('customer_id', $customerId)->orderByDesc('id');
        $row = $id ? $query->where('id', $id)->first() : $query->first();
        if (! $row) {
            return ['success' => false, 'error' => 'not_found', 'message' => "I couldn't find a previous quotation on this account."];
        }
        $lines = [];
        if (Schema::hasTable('product_quotation')) {
            $lines = \Illuminate\Support\Facades\DB::table('product_quotation')->where('quotation_id', $row->id)->get();
        }
        $pricing = app(\App\Services\Rental\RentalPricingService::class);
        $items = [];
        $bits = [];
        foreach ($lines as $line) {
            $product = Product::find($line->product_id);
            $qty = isset($line->qty) ? $line->qty : 1;
            $historical = isset($line->net_unit_price) ? $line->net_unit_price : null;
            $current = null;
            $name = $product ? $product->name : 'Item';
            if ($product) {
                $priced = $pricing->priceLine($product, $qty, 1);
                $current = ! empty($priced['success']) ? $priced['unit_price'] : null;
            }
            $items[] = [
                'product_id' => $product ? $product->id : (isset($line->product_id) ? $line->product_id : null),
                'name' => $name,
                'qty' => $qty,
                'historical_unit_price' => $historical,
                'current_unit_price' => $current,
            ];
            $bits[] = $name.' x '.$qty.' (historical '.$historical.', current '.$current.')';
        }
        $message = 'Quotation '.$row->reference_no.' is a previous record. Prices below marked historical are not a new quote. Current prices are from the catalogue today and still need staff approval before anything is sent.';
        if ($bits !== []) {
            $message .= ' '.implode('; ', $bits).'.';
        }

        return [
            'success' => true,
            'id' => $row->id,
            'reference' => $row->reference_no,
            'items' => $items,
            'message' => $message,
        ];
    }

    protected function toolGetQuotationStatus(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        $id = isset($params['quotation_id']) ? (int) $params['quotation_id'] : 0;
        $row = $id && $customerId ? Quotation::where('customer_id', $customerId)->find($id) : null;
        if (! $row) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => true, 'id' => $row->id, 'reference' => $row->reference_no, 'status' => Quotation::statusLabel($row->quotation_status)];
    }

    protected function toolGetCustomerBookings(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        if (! $customerId || ! Schema::hasTable('bookings')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $rows = Booking::where('customer_id', $customerId)->orderByDesc('id')->limit(8)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->bookingRow($row);
        }

        return ['success' => true, 'bookings' => $out, 'count' => count($out)];
    }

    protected function toolGetBookingStatus(array $params, array $context)
    {
        $customerId = $this->customerId($context);
        if (! $customerId || ! Schema::hasTable('bookings')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $id = isset($params['booking_id']) ? (int) $params['booking_id'] : 0;
        $row = $id ? Booking::where('customer_id', $customerId)->find($id) : null;
        if (! $row) {
            $rows = Booking::where('customer_id', $customerId)->orderByDesc('id')->limit(5)->get();
            if ($rows->count() === 1) {
                return ['success' => true, 'booking' => $this->bookingRow($rows->first()), 'ambiguous' => false];
            }
            if ($rows->count() > 1) {
                $list = [];
                foreach ($rows as $item) {
                    $list[] = $this->bookingRow($item);
                }

                return ['success' => true, 'ambiguous' => true, 'bookings' => $list];
            }

            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => true, 'booking' => $this->bookingRow($row), 'ambiguous' => false];
    }

    protected function toolGetCustomerPaymentSummary(array $params, array $context)
    {
        return ['success' => false, 'error' => 'verification_required'];
    }

    protected function toolGetInternshipSummary(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->summary($context, $params);
    }

    protected function toolGetCurrentInternshipTask(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->currentTask($context, $params);
    }

    protected function toolGetTaskInstructions(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->currentTask($context, $params);
    }

    protected function toolGetInternshipProgress(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->progress($context, $params);
    }

    protected function toolGetTaskMaterials(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->materials($context, $params);
    }

    protected function toolGetSubmissionStatus(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->submissionStatus($context, $params);
    }

    protected function toolPrepareInternshipSubmission(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->prepare($context, $params);
    }

    protected function toolSubmitInternshipWork(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->confirm($context, $params);
    }

    protected function toolAttachSubmissionFile(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->prepare($context, $params);
    }

    protected function toolAttachSubmissionLink(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->prepare($context, $params);
    }

    protected function toolRequestSupervisorHandover(array $params, array $context)
    {
        return app(\App\Services\Internship\InternshipWhatsAppService::class)->handover($context, $params);
    }

    protected function toolGetAttendanceStatus(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->status($context, $params);
    }

    protected function toolCheckIn(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->checkIn($context, $params);
    }

    protected function toolCheckOut(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->checkOut($context, $params);
    }

    protected function toolGetWorkHours(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->hours($context, $params);
    }

    protected function toolGetCurrentAssignment(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->assignment($context, $params);
    }

    protected function toolCheckInAssignment(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->checkInAssignment($context, $params);
    }

    protected function toolCheckOutAssignment(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->checkOutAssignment($context, $params);
    }

    protected function toolValidateAssignmentLocation(array $params, array $context)
    {
        $service = app(\App\Services\Attendance\AttendanceLocationService::class);
        if (! $service->validCoordinates(isset($params['latitude']) ? $params['latitude'] : null, isset($params['longitude']) ? $params['longitude'] : null)) {
            return ['success' => false, 'error' => 'invalid_location'];
        }

        return ['success' => true] + $service->verify(
            $params['latitude'],
            $params['longitude'],
            isset($params['expected_latitude']) ? $params['expected_latitude'] : null,
            isset($params['expected_longitude']) ? $params['expected_longitude'] : null,
            isset($params['radius']) ? $params['radius'] : null
        );
    }

    protected function toolRequestAttendanceCorrection(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->requestCorrection($context, $params);
    }

    protected function toolGetAttendanceCorrectionStatus(array $params, array $context)
    {
        return app(\App\Services\Attendance\AttendanceWhatsAppService::class)->correctionStatus($context, $params);
    }

    protected function toolGetCurrentLead(array $params, array $context)
    {
        $contactId = isset($context['contact_id']) ? $context['contact_id'] : null;
        if (! $contactId || ! Schema::hasTable('leads')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $lead = Lead::where('contact_id', $contactId)->orderByDesc('id')->first();
        if (! $lead) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => true, 'id' => $lead->id, 'status' => $lead->status, 'category' => $lead->category];
    }

    protected function toolRequestHumanHandover(array $params, array $context)
    {
        $allowed = [
            'USER_REQUESTED_HUMAN', 'AUTHORITY_REQUIRED', 'KNOWLEDGE_UNAVAILABLE', 'TOOL_FAILURE',
            'REPEATED_CLARIFICATION_FAILURE', 'POLICY_REQUIRED', 'COMPLAINT_ESCALATION',
        ];
        $category = strtoupper(trim((string) (isset($params['reason_category']) ? $params['reason_category'] : '')));
        if (! in_array($category, $allowed, true)) {
            $category = 'USER_REQUESTED_HUMAN';
        }
        $summary = trim((string) (isset($params['summary']) ? $params['summary'] : (isset($params['reason']) ? $params['reason'] : $category)));
        $conversation = isset($context['conversation']) ? $context['conversation'] : null;
        if ($conversation) {
            $this->handover->toHuman($conversation, $category.($summary !== '' ? ': '.$summary : ''));
        }

        return [
            'success' => true,
            'handed_over' => true,
            'reason_category' => $category,
            'summary' => $summary,
            'urgency' => isset($params['urgency']) ? $params['urgency'] : 'normal',
            'suggested_department' => isset($params['suggested_department']) ? $params['suggested_department'] : null,
        ];
    }

    protected function toolListAvailableDocuments(array $params, array $context)
    {
        $documents = app(\App\Services\WhatsApp\WhatsAppDocumentService::class)->listFor($context);

        return ['success' => true, 'documents' => $documents, 'message' => $documents === [] ? 'There are no documents available for this account on WhatsApp.' : null];
    }

    protected function toolFindMyDocuments(array $params, array $context)
    {
        return $this->toolRequestDocument($params, $context);
    }

    protected function toolRequestDocument(array $params, array $context)
    {
        return app(\App\Services\WhatsApp\WhatsAppDocumentService::class)->handle(
            $context,
            isset($params['text']) ? $params['text'] : '',
            $params,
            isset($params['provider_message_id']) ? $params['provider_message_id'] : null
        );
    }

    protected function toolGetDocumentRequestStatus(array $params, array $context)
    {
        $panel = isset($context['conversation'])
            ? app(\App\Services\WhatsApp\WhatsAppDocumentService::class)->panel($context['conversation'])
            : null;

        return ['success' => true, 'panel' => $panel, 'message' => $panel ? 'Request status: '.$panel['status'] : 'No document request is open.'];
    }

    protected function toolRequestVerification(array $params, array $context)
    {
        return $this->toolRequestDocument($params, $context);
    }

    protected function toolVerifyOtp(array $params, array $context)
    {
        $params['verification_pending'] = 1;

        return $this->toolRequestDocument($params, $context);
    }

    protected function toolGetVerificationStatus(array $params, array $context)
    {
        return $this->toolGetDocumentRequestStatus($params, $context);
    }

    protected function toolSendAuthorizedDocument(array $params, array $context)
    {
        return $this->toolRequestDocument($params, $context);
    }

    protected function toolGetMyTenancy(array $params, array $context)
    {
        return $this->propertyTool('get_my_tenancy', $params, $context);
    }

    protected function toolGetRentBalance(array $params, array $context)
    {
        return $this->propertyTool('get_rent_balance', $params, $context);
    }

    protected function toolGetRentDueDate(array $params, array $context)
    {
        return $this->propertyTool('get_rent_due_date', $params, $context);
    }

    protected function toolGetRentPaymentHistory(array $params, array $context)
    {
        return $this->propertyTool('get_rent_payment_history', $params, $context);
    }

    protected function toolRejectPaymentClaim(array $params, array $context)
    {
        return $this->propertyTool('record_payment_claim', $params, $context);
    }

    protected function toolClarifyTenantBalance(array $params, array $context)
    {
        return app(\App\Services\Property\PropertyWhatsAppService::class)->clarify();
    }

    protected function toolGetMaintenanceRequests(array $params, array $context)
    {
        return $this->propertyTool('get_maintenance_requests', $params, $context);
    }

    protected function toolCreateMaintenanceRequest(array $params, array $context)
    {
        return $this->propertyTool('create_maintenance_request', $params, $context);
    }

    protected function toolGetMaintenanceStatus(array $params, array $context)
    {
        return $this->propertyTool('get_maintenance_status', $params, $context);
    }

    protected function toolAddMaintenanceAttachment(array $params, array $context)
    {
        return $this->propertyTool('add_maintenance_attachment', $params, $context);
    }

    protected function toolRequestTenantDocument(array $params, array $context)
    {
        return $this->toolRequestDocument($params, $context);
    }

    protected function toolListSupportedBillTypes(array $params, array $context)
    {
        return $this->propertyTool('list_supported_bill_types', $params, $context);
    }

    protected function toolCreateBillPaymentRequest(array $params, array $context)
    {
        return $this->propertyTool('create_bill_payment_request', $params, $context);
    }

    protected function toolGetBillPaymentRequest(array $params, array $context)
    {
        return $this->propertyTool('get_bill_payment_request', $params, $context);
    }

    protected function toolUpdateBillPaymentDetails(array $params, array $context)
    {
        return $this->propertyTool('update_bill_payment_details', $params, $context);
    }

    protected function toolConfirmBillPaymentRequest(array $params, array $context)
    {
        return $this->propertyTool('confirm_bill_payment_request', $params, $context);
    }

    protected function toolGetBillPaymentStatus(array $params, array $context)
    {
        return $this->propertyTool('get_bill_payment_status', $params, $context);
    }

    protected function toolRequestBillPaymentHandover(array $params, array $context)
    {
        return $this->propertyTool('request_bill_payment_handover', $params, $context);
    }

    protected function toolAttachBillImage(array $params, array $context)
    {
        return $this->propertyTool('attach_bill_image', $params, $context);
    }

    protected function propertyTool($name, array $params, array $context)
    {
        return app(\App\Services\Property\PropertyWhatsAppService::class)->handle($name, $params, $context);
    }

    protected function knowledge(array $categories)
    {
        if (! Schema::hasTable('assistant_knowledge')) {
            return [];
        }
        $rows = AssistantKnowledge::where('enabled', true)->whereIn('category', $categories)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['title' => $row->title, 'content' => $row->content];
        }

        return $out;
    }

    protected function customerId(array $context)
    {
        return isset($context['customer_id']) ? (int) $context['customer_id'] : 0;
    }

    protected function bookingRow(Booking $row)
    {
        return [
            'id' => $row->id,
            'reference' => $row->reference_no,
            'status' => $row->booking_status,
            'payment_status' => $row->payment_status,
            'grand_total' => null,
        ];
    }
}
