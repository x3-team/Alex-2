<?php

/**
 * Merge into Admin BlogController store/update validation. Do not replace.
 *
 * 'audience' => ['required', Rule::in(config('doctors.audiences'))],
 *
 * Default existing posts to patients (migration backfills).
 *
 * And pass audience in create/edit Inertia props:
 * 'audienceOptions' => [
 *     ['value' => 'patients', 'label' => 'Пациенты'],
 *     ['value' => 'doctors', 'label' => 'Врачи'],
 * ],
 *
 * Mount resources/js/Pages/Admin/Blog/AudienceField.patch.vue next to the
 * existing admin blog form (v-model="form.audience").
 */
