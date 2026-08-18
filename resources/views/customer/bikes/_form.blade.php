@php
    $inputClass = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full';
@endphp

<div>
    <x-input-label for="nickname" value="Bicycle Nickname" />
    <x-text-input id="nickname" name="nickname" type="text" class="block mt-1 w-full"
        :value="old('nickname', $bicycle?->nickname ?? '')" placeholder="e.g. My Road Bike, Daily Commuter" required autofocus />
    <x-input-error :messages="$errors->get('nickname')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="bicycle_type_id" value="Bicycle Type" />
    <select id="bicycle_type_id" name="bicycle_type_id" class="{{ $inputClass }}" required>
        <option value="">Select a type&hellip;</option>
        @foreach ($bicycleTypes as $type)
            <option value="{{ $type->id }}" @selected(old('bicycle_type_id', $bicycle?->bicycle_type_id ?? '') == $type->id)>
                {{ $type->name }}
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('bicycle_type_id')" class="mt-2" />
</div>

<div class="mt-6 pt-6 border-t border-gray-200">
    <p class="text-sm text-gray-500 mb-4">The rest is optional &mdash; fill in what you know.</p>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="brand" value="Brand" />
            <x-text-input id="brand" name="brand" type="text" class="block mt-1 w-full" :value="old('brand', $bicycle?->brand ?? '')" />
            <x-input-error :messages="$errors->get('brand')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="model" value="Model" />
            <x-text-input id="model" name="model" type="text" class="block mt-1 w-full" :value="old('model', $bicycle?->model ?? '')" />
            <x-input-error :messages="$errors->get('model')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="color" value="Color" />
            <x-text-input id="color" name="color" type="text" class="block mt-1 w-full" :value="old('color', $bicycle?->color ?? '')" />
            <x-input-error :messages="$errors->get('color')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="wheel_size" value="Wheel Size" />
            <x-text-input id="wheel_size" name="wheel_size" type="text" class="block mt-1 w-full" :value="old('wheel_size', $bicycle?->wheel_size ?? '')" placeholder="e.g. 700c, 26&quot;" />
            <x-input-error :messages="$errors->get('wheel_size')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="year" value="Year" />
            <x-text-input id="year" name="year" type="number" class="block mt-1 w-full" :value="old('year', $bicycle?->year ?? '')" />
            <x-input-error :messages="$errors->get('year')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="serial_number" value="Serial Number" />
            <x-text-input id="serial_number" name="serial_number" type="text" class="block mt-1 w-full" :value="old('serial_number', $bicycle?->serial_number ?? '')" />
            <x-input-error :messages="$errors->get('serial_number')" class="mt-2" />
        </div>
    </div>

    <div class="mt-4">
        <x-input-label for="notes" value="Notes" />
        <textarea id="notes" name="notes" rows="3" class="{{ $inputClass }}">{{ old('notes', $bicycle?->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>
</div>
