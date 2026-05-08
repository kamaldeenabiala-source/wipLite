<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimesheetEntryRequest;
use App\Http\Requests\UpdateTimesheetEntryRequest;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use Carbon\Carbon;
use Inertia\Inertia;

class TimesheetEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $entries = TimesheetEntry::with(['timesheet'])->get();
        return Inertia::render('Timesheets/TimesCard', [
            'entries' => $entries
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
public function store(StoreTimesheetEntryRequest $request)
{
    $validated = $request->validated();
    $employeeIds = $request->input('employee_ids', [$request->employee_id]);

    foreach ($employeeIds as $empId) {
        $timesheet = Timesheet::firstOrCreate(
            [
                'employee_id' => $empId,
                'period_start' => Carbon::parse($validated['date'])->startOfWeek()->format('Y-m-d'),
                'period_end' => Carbon::parse($validated['date'])->endOfWeek()->format('Y-m-d'),
            ],
            ['status' => 'brouillon']
        );

        if ($timesheet->status === 'soumis') continue;

        // --- DYNAMISATION DES HEURES PRÉVUES (PLANNED HOURS) ---
        $date = Carbon::parse($validated['date']);
        $dayName = strtolower($date->format('l')); // ex: "monday", "tuesday"...
        $columnName = $dayName . '_hours'; // ex: "monday_hours"

        // On cherche le planning assigné à l'employé à cette date
        $plannedHours = \DB::table('planning_assignments')
            ->join('planning_models', 'planning_assignments.planning_model_id', '=', 'planning_models.id')
            ->where('planning_assignments.employee_id', $empId)
            ->where('planning_assignments.start_date', '<=', $validated['date'])
            ->where(function($query) use ($validated) {
                $query->where('planning_assignments.end_date', '>=', $validated['date'])
                      ->orWhereNull('planning_assignments.end_date');
            })
            ->value("planning_models.$columnName") ?? 0; // 0 si aucun planning trouvé

        // --- CALCUL DU RÉEL ---
        $totalHours = 0;
        if ($validated['check_in'] && $validated['check_out']) {
            $start = Carbon::parse($validated['check_in']);
            $end = Carbon::parse($validated['check_out']);
            $totalHours = max(0, ($start->diffInMinutes($end) - ($validated['break_duration'] ?? 0)) / 60);
        }

        // --- SAUVEGARDE CRUD ---
        TimesheetEntry::updateOrCreate(
            ['timesheet_id' => $timesheet->id, 'date' => $validated['date']],
            [
                'check_in'       => $validated['check_in'],
                'check_out'      => $validated['check_out'],
                'break_duration' => $validated['break_duration'] ?? 0,
                'total_hours'    => $totalHours,
                'planned_hours'  => $plannedHours, // Valeur dynamique de la BDD !
                'overtime_hours' => $totalHours - $plannedHours, // Écart réel
                'comment'        => $validated['comment']
            ]
        );

        if ($timesheet->status === 'brouillon') {
            $timesheet->update(['status' => 'valide']);
        }
    }

    return back();
}






    /**
     * Display the specified resource.
     */
 public function show($employeeId, $date)
{
    // On cherche l'entrée existante via la relation timesheet -> employee_id
    $entry = TimesheetEntry::whereHas('timesheet', function ($query) use ($employeeId) {
            $query->where('employee_id', $employeeId);
        })
        ->where('date', $date)
        ->first();

    return response()->json($entry);
}


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TimesheetEntry $timesheetEntry)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTimesheetEntryRequest $request, TimesheetEntry $timesheetEntry)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
 public function destroy(TimesheetEntry $entry)
{
    $timesheet = $entry->timesheet;
    
    if ($timesheet->status !== 'soumis') {
        $entry->delete();
    }

    return back();
}

}
