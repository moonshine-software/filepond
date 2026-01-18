@props([
    'value' => '',
    'hiddenAttributes' => null,
    'dropzoneAttributes' => null,
])

<div x-data="filepond()" {{ $dropzoneAttributes?->except('x-data')?->class('filepond-wrapper') }}>
    <template x-for="file in files" :key="file">
        <input type="hidden" {{ $hiddenAttributes }} :value="file" />
    </template>
    <input
        type="file"
        x-ref="input"
        {{ $attributes->except(['accept', 'name'])->merge([]) }}
    />
</div>
