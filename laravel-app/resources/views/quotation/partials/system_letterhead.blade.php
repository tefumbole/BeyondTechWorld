{{-- Prefer: @php extract(\App\Support\Letterhead::viewVars(), EXTR_SKIP); @endphp in the parent view.
     Kept for backward compatibility if included. --}}
@php extract(\App\Support\Letterhead::viewVars(), EXTR_SKIP); @endphp
