<h2>Selected letters Subjects</h2><br>
@foreach($id_array as $id)
    @php
        $letter = \App\Letter::find($id)
    @endphp
    <div>
        <ul>
            <li>{{ $letter->subject }}</li>
        </ul>
    </div>
@endforeach
