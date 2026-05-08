<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimesheetRequest;
use App\Http\Requests\UpdateTimesheetRequest;
use App\Models\Timesheet;
use Inertia\Inertia;
use Illuminate\Http\Request;

class TimesheetController extends Controller 
{
    /**
     * Affiche la liste des feuilles de temps (Calendrier).
     */
    public function index()
{
    $user = auth()->user();
    $employee = $user->employee;
    $role = $user->role->name; // 'SUP' ou 'CP' ou 'TC'

    $query = Timesheet::with(['employee', 'validator', 'entries']);

    if ($role === 'SUP') {
        // Le SUP ne voit que les TC qui lui sont assignés dans la table assignments
        $query->whereHas('employee.assignments', function ($q) use ($employee) {
            $q->where('manager_id', $employee->id)
              ->where('status', 'actif');
        });
    } 
    elseif ($role === 'CP') {
        // Le CP voit les SUP (et TC) qui lui sont assignés
        $query->whereHas('employee.assignments', function ($q) use ($employee) {
            $q->where('manager_id', $employee->id)
              ->where('status', 'actif');
        });
    } 
    elseif ($role === 'TC') {
        // Le TC ne voit que sa propre ligne
        $query->where('employee_id', $employee->id);
    }

    $calendar = $query->latest()->get();

    return Inertia::render('Timesheets/Calendar', [
        'calendar' => $calendar
    ]);
}


    /**
     * Affiche le formulaire de création.
     */
    public function create()
    {
        return Inertia::render('Timesheets/Create');
    }

    /**
     * Enregistre une nouvelle ressource.
     */
    public function store(StoreTimesheetRequest $request)
    {
        Timesheet::create($request->validated());
        return redirect()->route('timesheets.index');
    }

    /**
     * Affiche une ressource spécifique.
     */
    public function show(Timesheet $timesheet)
    {
        return Inertia::render('Timesheets/Show', [
            'timesheet' => $timesheet->load(['employee', 'validator', 'entries'])
        ]);
    }

    /**
     * Affiche le formulaire d'édition.
     */
    public function edit(Timesheet $timesheet)
    {
        return Inertia::render('Timesheets/Edit', [
            'timesheet' => $timesheet
        ]);
    }

    /**
     * Met à jour la ressource.
     */
    public function update(UpdateTimesheetRequest $request, Timesheet $timesheet)
    {

    }

    /**
     * Supprime la ressource.
     */
    public function destroy(Timesheet $timesheet)
    {

    }

    /**
 * Soumet définitivement la feuille de temps (Verrouillage).
 */
/**
 * Validation finale par le Chef de Plateau (CP)
 */
public function submit(Timesheet $timesheet)
{
    // 1. Vérification de sécurité : Seul un CP (ou Admin) devrait pouvoir faire ça
    // if (auth()->user()->role->code !== 'CP') { abort(403); }

    // 2. Mise à jour de la feuille de temps
    $timesheet->update([
        'status' => 'soumis',
        'validated_by' => auth()->user()->employee->id, // L'ID de l'employé qui valide
        'validated_at' => now(), // Horodatage précis
    ]);

    // 3. Optionnel : On peut aussi verrouiller les entrées individuelles
    // $timesheet->entries()->update(['status' => 'soumis']);

    return back()->with('success', 'La feuille de temps a été validée par ' . auth()->user()->name);
}


}
