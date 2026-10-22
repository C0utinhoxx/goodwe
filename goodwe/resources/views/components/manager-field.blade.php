@props(['name', 'label', 'type'=>'text', 'value'=>null, 'required'=>true])
<label class="gw-field flex min-w-0 flex-col gap-2 text-sm font-medium">
    <span>{{ $label }} @unless($required)<span class="font-normal text-gray-500">{{ __('screen.opcional') }}</span>@endunless</span>
    <input id="{{ $name }}" @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif name="{{ $name }}" type="{{ $type }}" value="{{ old($name,$value) }}" @required($required) {{ $attributes->class(['w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 py-3 text-gray-900 outline-none focus:border-[#c62828] dark:border-[#343843] dark:bg-[#181b22] dark:text-white']) }}>
    @error($name)<span id="{{ $name }}-error" class="text-sm font-normal text-[#c62828] dark:text-red-300">{{ $message }}</span>@enderror
</label>
