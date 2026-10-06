<div class="wa-card">
    <h5>Official WhatsApp number</h5>
    @if(!empty($businessLine['configured']) && !empty($businessLine['display']))
        <p>This Hub sends approved templates from <strong>{{ $businessLine['display'] }}</strong>. Conversations, groups, and documents stay on the linked WhatsApp session.</p>
    @else
        <p>The official WhatsApp number is not configured yet. Approved templates cannot be sent until that number is saved in messaging settings.</p>
    @endif
    <table class="table table-sm mb-0">
        <thead>
            <tr>
                <th>Template</th>
                <th>Category</th>
                <th>What it sends</th>
            </tr>
        </thead>
        <tbody>
            @foreach($businessLine['templates'] as $template)
                <tr>
                    <td>{{ $template['name'] }}</td>
                    <td>{{ $template['category'] }}</td>
                    <td>{{ $template['sends'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
