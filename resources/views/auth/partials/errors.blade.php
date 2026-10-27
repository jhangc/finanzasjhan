@if ($errors->any())
    <div class="rounded-xl bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
