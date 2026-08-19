@php
    $initialStep = 1;
    if ($errors->has('appointment_date')) $initialStep = 4;
    if ($errors->has('remarks')) $initialStep = 3;
    if ($errors->has('bicycle_parts') || $errors->has('bicycle_parts.*')) $initialStep = 2;
    if ($errors->has('bicycle_id')) $initialStep = 1;

    $oldParts = array_map('intval', old('bicycle_parts', []));
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Book a Repair" class="mb-0" />
    </x-slot>

    @if ($bicycles->isEmpty())
        <x-empty-state
            title="Add a bicycle first"
            description="You'll need to register a bicycle before you can book a repair for it."
            action-label="Add Bicycle"
            :action-href="route('customer.bikes.create')"
        />
    @else
        <div x-data="{
                step: {{ $initialStep }},
                bicycleId: @js(old('bicycle_id') ? (int) old('bicycle_id') : null),
                selectedParts: @js($oldParts),
                activeCategory: null,
                remarks: @js(old('remarks', '')),
                appointmentDate: @js(old('appointment_date', '')),
                get selectedBicycle() {
                    return this.bicycles.find(b => b.id === this.bicycleId);
                },
                bicycles: @js($bicycles->map(fn ($b) => ['id' => $b->id, 'nickname' => $b->nickname, 'type' => $b->bicycleType->name])),
                categories: @js($categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])),
                parts: @js($categories->flatMap->bicycleParts->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'category_id' => $p->bicycle_part_category_id])),
                get partsForActiveCategory() {
                    return this.parts.filter(p => p.category_id === this.activeCategory);
                },
                get selectedPartNames() {
                    return this.parts.filter(p => this.selectedParts.includes(p.id)).map(p => p.name);
                },
                togglePart(id) {
                    this.selectedParts = this.selectedParts.includes(id)
                        ? this.selectedParts.filter(p => p !== id)
                        : [...this.selectedParts, id];
                },
                categoryHasSelection(categoryId) {
                    return this.parts.some(p => p.category_id === categoryId && this.selectedParts.includes(p.id));
                },
                next() { if (this.step < 5) this.step++ },
                back() { if (this.step > 1) this.step-- },
            }"
        >
            <!-- Step indicator -->
            <div class="flex items-center gap-1 mb-5">
                <template x-for="n in 5" :key="n">
                    <div class="h-1.5 flex-1 rounded-full" :class="n <= step ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                </template>
            </div>

            <form method="POST" action="{{ route('customer.repairs.store') }}">
                @csrf

                <!-- Step 1: Choose Bicycle -->
                <div x-show="step === 1" x-cloak>
                    <h2 class="text-base font-semibold text-gray-900 mb-3">Which bicycle?</h2>
                    <div class="space-y-2">
                        <template x-for="bike in bicycles" :key="bike.id">
                            <label class="flex items-center justify-between rounded-xl border p-4 cursor-pointer"
                                :class="bicycleId === bike.id ? 'border-indigo-600 ring-1 ring-indigo-600 bg-indigo-50' : 'border-gray-200 bg-white'">
                                <div>
                                    <p class="font-medium text-gray-900" x-text="bike.nickname"></p>
                                    <p class="text-xs text-gray-500" x-text="bike.type"></p>
                                </div>
                                <input type="radio" name="bicycle_id" :value="bike.id" x-model.number="bicycleId" class="text-indigo-600 focus:ring-indigo-500">
                            </label>
                        </template>
                    </div>
                    <x-input-error :messages="$errors->get('bicycle_id')" class="mt-2" />

                    <button type="button" @click="next()" :disabled="bicycleId === null"
                        class="mt-6 w-full rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed">
                        Continue
                    </button>
                </div>

                <!-- Step 2: What needs attention -->
                <div x-show="step === 2" x-cloak>
                    <h2 class="text-base font-semibold text-gray-900 mb-1">What needs attention?</h2>
                    <p class="text-sm text-gray-500 mb-3">Pick a category, then select the parts. You can select from more than one category.</p>

                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <template x-for="category in categories" :key="category.id">
                            <button type="button" @click="activeCategory = category.id"
                                class="relative rounded-lg border px-3 py-2.5 text-sm font-medium text-left"
                                :class="activeCategory === category.id ? 'border-indigo-600 ring-1 ring-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-700'">
                                <span x-text="category.name"></span>
                                <span x-show="categoryHasSelection(category.id)" class="ml-1 inline-block w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                            </button>
                        </template>
                    </div>

                    <div x-show="activeCategory !== null" class="space-y-2 mb-2">
                        <template x-for="part in partsForActiveCategory" :key="part.id">
                            <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 cursor-pointer">
                                <span class="text-sm text-gray-900" x-text="part.name"></span>
                                <input type="checkbox"
                                    :checked="selectedParts.includes(part.id)" @change="togglePart(part.id)"
                                    class="rounded text-indigo-600 focus:ring-indigo-500">
                            </label>
                        </template>
                    </div>

                    <p x-show="activeCategory === null" class="text-sm text-gray-400 text-center py-6">
                        Choose a category above to see its parts.
                    </p>

                    <!--
                        The checkboxes above only exist in the DOM for the
                        currently active category (x-for over a filtered
                        list destroys the others), so they can't be relied
                        on to submit the full selection natively. These
                        hidden inputs always reflect the complete
                        selectedParts array regardless of which category
                        tab is open.
                    -->
                    <template x-for="id in selectedParts" :key="id">
                        <input type="hidden" name="bicycle_parts[]" :value="id">
                    </template>

                    <x-input-error :messages="$errors->get('bicycle_parts')" class="mt-2" />

                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="back()" class="rounded-lg bg-white px-5 py-3 text-sm font-semibold text-gray-900 border border-gray-300 hover:bg-gray-50">
                            Back
                        </button>
                        <button type="button" @click="next()" :disabled="selectedParts.length === 0"
                            class="flex-1 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed">
                            Continue (<span x-text="selectedParts.length"></span> selected)
                        </button>
                    </div>
                </div>

                <!-- Step 3: Describe the problem -->
                <div x-show="step === 3" x-cloak>
                    <h2 class="text-base font-semibold text-gray-900 mb-1">Describe the problem</h2>
                    <p class="text-sm text-gray-500 mb-3">Optional &mdash; tell us anything that might help. Not sure what's wrong? That's okay, just say so.</p>

                    <textarea name="remarks" rows="5" x-model="remarks" placeholder="e.g. Chain makes noise especially when shifting uphill. Not sure — please inspect."
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full"></textarea>
                    <x-input-error :messages="$errors->get('remarks')" class="mt-2" />

                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="back()" class="rounded-lg bg-white px-5 py-3 text-sm font-semibold text-gray-900 border border-gray-300 hover:bg-gray-50">
                            Back
                        </button>
                        <button type="button" @click="next()" class="flex-1 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                            Continue
                        </button>
                    </div>
                </div>

                <!-- Step 4: Choose Appointment -->
                <div x-show="step === 4" x-cloak>
                    <h2 class="text-base font-semibold text-gray-900 mb-3">Choose an appointment date</h2>

                    <x-input-label for="appointment_date" value="Date" />
                    <input type="date" id="appointment_date" name="appointment_date" x-model="appointmentDate"
                        min="{{ now()->toDateString() }}"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <x-input-error :messages="$errors->get('appointment_date')" class="mt-2" />

                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="back()" class="rounded-lg bg-white px-5 py-3 text-sm font-semibold text-gray-900 border border-gray-300 hover:bg-gray-50">
                            Back
                        </button>
                        <button type="button" @click="next()" :disabled="!appointmentDate"
                            class="flex-1 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed">
                            Continue
                        </button>
                    </div>
                </div>

                <!-- Step 5: Review -->
                <div x-show="step === 5" x-cloak>
                    <h2 class="text-base font-semibold text-gray-900 mb-3">Review your booking</h2>

                    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 mb-4">
                        <div class="px-4 py-3">
                            <p class="text-xs text-gray-500">Bicycle</p>
                            <p class="text-sm font-medium text-gray-900" x-text="selectedBicycle?.nickname"></p>
                        </div>
                        <div class="px-4 py-3">
                            <p class="text-xs text-gray-500 mb-1">Needs Attention</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="name in selectedPartNames" :key="name">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700" x-text="name"></span>
                                </template>
                            </div>
                        </div>
                        <div class="px-4 py-3" x-show="remarks">
                            <p class="text-xs text-gray-500">Remarks</p>
                            <p class="text-sm text-gray-900" x-text="remarks"></p>
                        </div>
                        <div class="px-4 py-3">
                            <p class="text-xs text-gray-500">Appointment</p>
                            <p class="text-sm font-medium text-gray-900" x-text="appointmentDate"></p>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" @click="back()" class="rounded-lg bg-white px-5 py-3 text-sm font-semibold text-gray-900 border border-gray-300 hover:bg-gray-50">
                            Back
                        </button>
                        <button type="submit" class="flex-1 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                            Confirm Booking
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</x-app-layout>
