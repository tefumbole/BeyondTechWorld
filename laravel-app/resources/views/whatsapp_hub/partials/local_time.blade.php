<script>
window.hubWhen = function (value) {
    if (!value) return null;
    var date = new Date(value);
    return isNaN(date.getTime()) ? null : date;
};
window.hubPad = function (n) {
    return (n < 10 ? '0' : '') + n;
};
window.hubDayKey = function (value) {
    var date = window.hubWhen(value);
    if (!date) return '';
    return date.getFullYear() + '-' + window.hubPad(date.getMonth() + 1) + '-' + window.hubPad(date.getDate());
};
window.hubDayLabel = function (value) {
    var date = window.hubWhen(value);
    if (!date) return '';
    var now = new Date();
    var start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var day = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    var diff = Math.round((start - day) / 86400000);
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var label = months[date.getMonth()] + ' ' + date.getDate();
    if (date.getFullYear() !== now.getFullYear()) label += ', ' + date.getFullYear();
    return label;
};
window.hubClock = function (value) {
    var date = window.hubWhen(value);
    if (!date) return '';
    return window.hubPad(date.getHours()) + ':' + window.hubPad(date.getMinutes());
};
window.hubListTime = function (value) {
    var date = window.hubWhen(value);
    if (!date) return '';
    if (window.hubDayLabel(value) === 'Today') return window.hubClock(value);
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return months[date.getMonth()] + ' ' + date.getDate();
};
window.hubStamp = function (value) {
    var date = window.hubWhen(value);
    if (!date) return '';
    return window.hubDayLabel(value) + ' ' + window.hubClock(value);
};
window.applyLocalHubTimes = function () {
    document.querySelectorAll('.chat-time[data-at]').forEach(function (node) {
        var label = window.hubListTime(node.getAttribute('data-at'));
        if (label) node.textContent = label;
    });
    document.querySelectorAll('time[data-at] > .clock, .wa-meta[data-at] > .clock').forEach(function (clock) {
        var at = clock.parentNode.getAttribute('data-at');
        var label = clock.parentNode.classList.contains('wa-meta') ? window.hubStamp(at) : window.hubClock(at);
        if (label) clock.textContent = label;
    });
    document.querySelectorAll('#chatStream').forEach(function (stream) {
        var previous = '';
        stream.querySelectorAll('.bubble[data-at]').forEach(function (bubble) {
            var at = bubble.getAttribute('data-at');
            var key = window.hubDayKey(at);
            var clock = bubble.querySelector('.clock');
            if (clock) {
                var shown = window.hubClock(at);
                if (shown) clock.textContent = shown;
            }
            var prev = bubble.previousElementSibling;
            if (key && key !== previous) {
                var chip = prev && prev.classList && prev.classList.contains('day-chip') ? prev : null;
                if (!chip) {
                    chip = document.createElement('div');
                    chip.className = 'day-chip';
                    bubble.parentNode.insertBefore(chip, bubble);
                }
                chip.textContent = window.hubDayLabel(at);
                previous = key;
            } else if (prev && prev.classList && prev.classList.contains('day-chip')) {
                prev.parentNode.removeChild(prev);
            }
        });
    });
};
</script>
