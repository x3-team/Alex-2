<?php

/**
 * Merge into Admin BlogController store/update validation:
 *
 * 'audience' => ['required', Rule::in(config('doctors.audiences'))],
 *
 * And pass audience in create/edit Inertia props:
 * 'audienceOptions' => [
 *     ['value' => 'patients', 'label' => 'Пациенты'],
 *     ['value' => 'doctors', 'label' => 'Врачи'],
 * ],
 */
