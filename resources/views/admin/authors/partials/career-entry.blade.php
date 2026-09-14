<div class="flex gap-3 items-start p-3 bg-gray-50 rounded-lg border border-gray-200 career-entry">
    <div class="flex-1">
        <label class="block text-xs font-medium text-gray-500 mb-1">Год начала *</label>
        <input
                type="number"
                name="career_history[{{ $index }}][year_from]"
                value="{{ $year_from }}"
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                placeholder="2020"
                min="1950"
                max="{{ date('Y') }}"
        />
        @error("career_history.{$index}.year_from")
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div class="flex-1">
        <label class="block text-xs font-medium text-gray-500 mb-1">Год окончания</label>
        <input
                type="number"
                name="career_history[{{ $index }}][year_to]"
                value="{{ $year_to }}"
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                placeholder="2023 или пусто"
                min="1950"
                max="{{ date('Y') }}"
        />
        @error("career_history.{$index}.year_to")
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div class="flex-[2]">
        <label class="block text-xs font-medium text-gray-500 mb-1">Место работы / Должность</label>
        <input
                type="text"
                name="career_history[{{ $index }}][place]"
                value="{{ $place }}"
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                placeholder="Врач в клинике Х"
        />
    </div>
    <button
            type="button"
            onclick="removeCareerEntry(this)"
            class="mt-6 p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition-colors"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
    </button>
</div>