<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Employee;
use App\Models\Assignment;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\PlanningAssignment;
use App\Models\PlanningModel;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ReportingController extends Controller
{
    /**
     * Dashboard principal analytics (Admin uniquement)
     */
    public function index(Request $request)
    {
        $period_start = $request->get('period_start', Carbon::now()->startOfMonth()->toDateString());
        $period_end   = $request->get('period_end',   Carbon::now()->endOfMonth()->toDateString());

        return Inertia::render('Reporting/Dashboard', [
            'kpis'               => $this->getKpis(),
            'campaignStats'      => $this->getCampaignStats($period_start, $period_end),
            'employeeStats'      => $this->getEmployeeStats($period_start, $period_end),
            'planningVsRealised' => $this->getPlanningVsRealised($period_start, $period_end),
            'monthlyTrend'       => $this->getMonthlyTrend(),
            'period'             => ['start' => $period_start, 'end' => $period_end],
        ]);
    }

    // -------------------------------------------------------------------------
    // KPIs GLOBAUX
    // -------------------------------------------------------------------------
    private function getKpis(): array
    {
        $totalEmployees    = Employee::count();
        $activeEmployees   = Employee::where('status', 'actif')->count();
        $activeCampaigns   = Campaign::where('status', 'active')->count();
        $assignedEmployees = Assignment::where('status', 'actif')
                                ->distinct('employee_id')->count('employee_id');
        $unassigned        = $activeEmployees - $assignedEmployees;

        // Taux d'occupation global
        $occupancyRate = $activeEmployees > 0
            ? round(($assignedEmployees / $activeEmployees) * 100, 1)
            : 0;

        // Heures totales ce mois
        $currentMonthHours = TimesheetEntry::whereHas('timesheet', function ($q) {
            $q->where('period_start', '>=', Carbon::now()->startOfMonth())
              ->where('period_end',   '<=', Carbon::now()->endOfMonth());
        })->sum('total_hours');

        // Heures supplémentaires ce mois
        $overtimeHours = TimesheetEntry::whereHas('timesheet', function ($q) {
            $q->where('period_start', '>=', Carbon::now()->startOfMonth())
              ->where('period_end',   '<=', Carbon::now()->endOfMonth());
        })->sum('overtime_hours');

        // Plannings en attente de validation
        $pendingPlannings = PlanningAssignment::where('status', 'en_attente')->count();

        return [
            'total_employees'     => $totalEmployees,
            'active_employees'    => $activeEmployees,
            'active_campaigns'    => $activeCampaigns,
            'assigned_employees'  => $assignedEmployees,
            'unassigned_employees'=> $unassigned,
            'occupancy_rate'      => $occupancyRate,
            'current_month_hours' => round($currentMonthHours, 1),
            'overtime_hours'      => round($overtimeHours, 1),
            'pending_plannings'   => $pendingPlannings,
        ];
    }

    // -------------------------------------------------------------------------
    // STATS PAR CAMPAGNE
    // -------------------------------------------------------------------------
    private function getCampaignStats(string $start, string $end): array
    {
        $campaigns = Campaign::withCount([
            'assignments as total_assigned' => fn($q) => $q->where('status', 'actif'),
        ])->get();

        return $campaigns->map(function ($campaign) use ($start, $end) {
            // Décomposition hiérarchique : CP / SUP / TC
            $breakdown = Assignment::where('campaign_id', $campaign->id)
                ->where('status', 'actif')
                ->join('positions', 'assignments.position_id', '=', 'positions.id')
                ->select('positions.name as position', DB::raw('count(*) as count'))
                ->groupBy('positions.name')
                ->pluck('count', 'position')
                ->toArray();

            // Heures réalisées sur la période
            $hoursRealised = TimesheetEntry::join('timesheets', 'timesheet_entries.timesheet_id', '=', 'timesheets.id')
                ->join('assignments', 'timesheets.employee_id', '=', 'assignments.employee_id')
                ->where('assignments.campaign_id', $campaign->id)
                ->whereBetween('timesheets.period_start', [$start, $end])
                ->sum('timesheet_entries.total_hours');

            // Heures planifiées sur la période
            $hoursPlanned = TimesheetEntry::join('timesheets', 'timesheet_entries.timesheet_id', '=', 'timesheets.id')
                ->join('assignments', 'timesheets.employee_id', '=', 'assignments.employee_id')
                ->where('assignments.campaign_id', $campaign->id)
                ->whereBetween('timesheets.period_start', [$start, $end])
                ->sum('timesheet_entries.planned_hours');

            $variance = $hoursPlanned > 0
                ? round((($hoursRealised - $hoursPlanned) / $hoursPlanned) * 100, 1)
                : 0;

            return [
                'id'             => $campaign->id,
                'name'           => $campaign->name,
                'status'         => $campaign->status,
                'start_date'     => $campaign->start_date,
                'end_date'       => $campaign->end_date,
                'total_assigned' => $campaign->total_assigned,
                'breakdown'      => $breakdown,
                'hours_planned'  => round($hoursPlanned, 1),
                'hours_realised' => round($hoursRealised, 1),
                'variance_pct'   => $variance,
            ];
        })->toArray();
    }

    // -------------------------------------------------------------------------
    // STATS PAR EMPLOYÉ
    // -------------------------------------------------------------------------
    private function getEmployeeStats(string $start, string $end): array
    {
        return Employee::with(['position', 'user'])
            ->where('status', 'actif')
            ->get()
            ->map(function ($employee) use ($start, $end) {
                // Dernière affectation active
                $assignment = Assignment::with(['campaign', 'manager'])
                    ->where('employee_id', $employee->id)
                    ->where('status', 'actif')
                    ->latest()
                    ->first();

                // Heures sur la période
                $timesheetData = TimesheetEntry::join('timesheets', 'timesheet_entries.timesheet_id', '=', 'timesheets.id')
                    ->where('timesheets.employee_id', $employee->id)
                    ->whereBetween('timesheets.period_start', [$start, $end])
                    ->select(
                        DB::raw('SUM(total_hours) as total_hours'),
                        DB::raw('SUM(planned_hours) as planned_hours'),
                        DB::raw('SUM(overtime_hours) as overtime_hours'),
                        DB::raw('COUNT(CASE WHEN absence_type IS NOT NULL THEN 1 END) as absence_days')
                    )
                    ->first();

                $tH = (float)($timesheetData->total_hours  ?? 0);
                $pH = (float)($timesheetData->planned_hours ?? 0);
                $gap = round($tH - $pH, 1);

                return [
                    'id'              => $employee->id,
                    'matricule'       => $employee->matricule,
                    'name'            => $employee->first_name . ' ' . $employee->last_name,
                    'position'        => $employee->position?->name,
                    'campaign'        => $assignment?->campaign?->name ?? '—',
                    'status'          => $employee->status,
                    'hours_planned'   => round($pH, 1),
                    'hours_realised'  => round($tH, 1),
                    'overtime_hours'  => round((float)($timesheetData->overtime_hours ?? 0), 1),
                    'absence_days'    => (int)($timesheetData->absence_days ?? 0),
                    'gap'             => $gap,
                    'gap_pct'         => $pH > 0 ? round(($gap / $pH) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('hours_realised')
            ->values()
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // ÉCARTS PLANNING VS RÉALISÉ
    // -------------------------------------------------------------------------
    private function getPlanningVsRealised(string $start, string $end): array
    {
        // Agrégé par semaine
        $rows = TimesheetEntry::join('timesheets', 'timesheet_entries.timesheet_id', '=', 'timesheets.id')
            ->whereBetween('timesheet_entries.date', [$start, $end])
            ->select(
                DB::raw('YEARWEEK(timesheet_entries.date, 1) as week_key'),
                DB::raw('MIN(timesheet_entries.date) as week_start'),
                DB::raw('SUM(planned_hours) as planned'),
                DB::raw('SUM(total_hours) as realised'),
                DB::raw('SUM(overtime_hours) as overtime'),
                DB::raw('COUNT(CASE WHEN absence_type IS NOT NULL THEN 1 END) as absences')
            )
            ->groupBy('week_key')
            ->orderBy('week_key')
            ->get();

        return $rows->map(fn($row) => [
            'week'     => Carbon::parse($row->week_start)->format('d/m'),
            'planned'  => round((float)$row->planned,  1),
            'realised' => round((float)$row->realised, 1),
            'overtime' => round((float)$row->overtime, 1),
            'absences' => (int)$row->absences,
            'gap'      => round((float)$row->realised - (float)$row->planned, 1),
        ])->toArray();
    }

    // -------------------------------------------------------------------------
    // TENDANCE MENSUELLE (12 derniers mois)
    // -------------------------------------------------------------------------
    private function getMonthlyTrend(): array
    {
        $rows = TimesheetEntry::join('timesheets', 'timesheet_entries.timesheet_id', '=', 'timesheets.id')
            ->where('timesheet_entries.date', '>=', Carbon::now()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw('DATE_FORMAT(timesheet_entries.date, "%Y-%m") as month'),
                DB::raw('SUM(total_hours) as hours'),
                DB::raw('COUNT(DISTINCT timesheets.employee_id) as active_employees')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->map(fn($row) => [
            'month'            => Carbon::createFromFormat('Y-m', $row->month)->format('M Y'),
            'hours'            => round((float)$row->hours, 0),
            'active_employees' => (int)$row->active_employees,
        ])->toArray();
    }

    // -------------------------------------------------------------------------
    // EXPORT JSON (base pour Excel/PDF côté frontend)
    // -------------------------------------------------------------------------
    public function exportData(Request $request)
    {
        $type         = $request->get('type', 'campaigns');
        $period_start = $request->get('period_start', Carbon::now()->startOfMonth()->toDateString());
        $period_end   = $request->get('period_end',   Carbon::now()->endOfMonth()->toDateString());

        $data = match ($type) {
            'campaigns' => $this->getCampaignStats($period_start, $period_end),
            'employees' => $this->getEmployeeStats($period_start, $period_end),
            'planning'  => $this->getPlanningVsRealised($period_start, $period_end),
            default     => [],
        };

        return response()->json([
            'type'   => $type,
            'period' => ['start' => $period_start, 'end' => $period_end],
            'data'   => $data,
        ]);
    }
}
