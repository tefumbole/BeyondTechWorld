<div class="cc" data-cc>
    <input type="hidden" name="{{ $name ?? 'country_code' }}" value="+237" data-cc-value>
    <button type="button" class="cc-btn" data-cc-btn aria-expanded="false" aria-label="Country code">
        <span data-cc-flag>🇨🇲</span>
        <span data-cc-dial>+237</span>
        <span class="cc-caret">▾</span>
    </button>
    <div class="cc-menu" data-cc-menu hidden>
        <input type="search" data-cc-search placeholder="Search country or code" autocomplete="off">
        <ul data-cc-list></ul>
    </div>
</div>
