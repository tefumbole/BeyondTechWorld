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
@push('gbhs-dial')
<script type="application/json" id="gbhs-countries">@json(\App\Support\CountryDialCodes::picker())</script>
<script>
(function () {
    if (window.gbhsDialReady) return;
    window.gbhsDialReady = true;
    var countries = [];
    try { countries = JSON.parse(document.getElementById('gbhs-countries').textContent || '[]'); } catch (e) {}
    document.querySelectorAll('[data-cc]').forEach(function (root) {
        var hidden = root.querySelector('[data-cc-value]');
        var btn = root.querySelector('[data-cc-btn]');
        var menu = root.querySelector('[data-cc-menu]');
        var search = root.querySelector('[data-cc-search]');
        var list = root.querySelector('[data-cc-list]');
        var flag = root.querySelector('[data-cc-flag]');
        var dial = root.querySelector('[data-cc-dial]');
        function render() {
            var q = (search.value || '').toLowerCase().trim();
            var rows = !q ? countries : countries.filter(function (c) {
                return (c.name + ' ' + c.code).toLowerCase().indexOf(q) !== -1;
            });
            list.innerHTML = '';
            rows.forEach(function (c) {
                var li = document.createElement('li');
                if (c.code === hidden.value && c.name === (root.getAttribute('data-country') || 'Cameroon') && !q) {
                    li.className = 'is-on';
                }
                li.innerHTML = '<span class="flag"></span><span class="name"></span><span class="dial"></span>';
                li.querySelector('.flag').textContent = c.flag || '';
                li.querySelector('.name').textContent = c.name;
                li.querySelector('.dial').textContent = c.code;
                li.addEventListener('click', function () {
                    hidden.value = c.code;
                    flag.textContent = c.flag || '';
                    dial.textContent = c.code;
                    root.setAttribute('data-country', c.name);
                    menu.hidden = true;
                    btn.setAttribute('aria-expanded', 'false');
                    hidden.dispatchEvent(new Event('change', {bubbles: true}));
                });
                list.appendChild(li);
            });
        }
        btn.addEventListener('click', function () {
            menu.hidden = !menu.hidden;
            btn.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
            if (!menu.hidden) { render(); search.focus(); }
        });
        search.addEventListener('input', render);
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
        });
    });
})();
</script>
@endpush
