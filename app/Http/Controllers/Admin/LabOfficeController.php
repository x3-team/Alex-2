<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabOffice;
use App\Support\LabOfficeCsv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Офисы лаборатории: список, правка, выключение и импорт CSV. Удаления нет — офис выключается. */
class LabOfficeController extends Controller
{
    public function index(Request $request)
    {
        $city = $request->string('city')->toString();
        $status = $request->string('status')->toString();

        $offices = LabOffice::query()
            ->when($city !== '', fn ($q) => $q->where('city', $city))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('city')->orderBy('sort')->orderBy('name')
            ->get();

        return Inertia::render('Admin/LabOffices/Index', [
            'offices' => $offices->map(fn (LabOffice $o) => $o->toArray() + ['map_url' => $o->mapUrl()]),
            'cities' => LabOffice::query()->distinct()->orderBy('city')->pluck('city'),
            'filters' => ['city' => $city, 'status' => $status],
            'importErrors' => session('import_errors', []),
        ]);
    }

    public function store(Request $request)
    {
        LabOffice::create($this->validated($request));

        return back()->with('success', 'Офис добавлен');
    }

    public function update(Request $request, LabOffice $labOffice)
    {
        $labOffice->update($this->validated($request, $labOffice));

        return back()->with('success', 'Офис сохранён');
    }

    public function toggle(LabOffice $labOffice)
    {
        $labOffice->update(['is_active' => ! $labOffice->is_active]);

        return back()->with('success', $labOffice->is_active ? 'Офис включён' : 'Офис выключен');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|max:2048|mimes:csv,txt']);

        $result = LabOfficeCsv::import((string) file_get_contents($request->file('file')->getRealPath()));
        $message = "Импорт: добавлено {$result['created']}, обновлено {$result['updated']}";

        if ($result['errors'] !== []) {
            return back()
                ->with('error', $message.', с ошибками: '.count($result['errors']))
                ->with('import_errors', array_slice($result['errors'], 0, 50));
        }

        return back()->with('success', $message);
    }

    public function template()
    {
        return response(LabOfficeCsv::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="lab-offices-template.csv"',
        ]);
    }

    private function validated(Request $request, ?LabOffice $office = null): array
    {
        $rules = LabOfficeCsv::rules();
        $rules['lis_code'] = ['nullable', 'string', 'max:64', Rule::unique('lab_offices', 'lis_code')->ignore($office?->id)];
        $rules['sort'] = 'nullable|integer|min:0|max:100000';

        $data = $request->validate($rules);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['accepts_alex2'] = $request->boolean('accepts_alex2', true);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
