<?php

namespace App\Models;

<<<<<<< HEAD
use Illuminate\Database\Eloquent\Model;
=======
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
>>>>>>> 2b06f4214a1276acce479a4c988985fbfc7914fc
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TimesheetEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'timesheet_id',
        'date',
        'check_in',
        'check_out',
        'break_duration',
        'total_hours',
        'planned_hours',
        'overtime_hours',
        'absence_type',
<<<<<<< HEAD
        'comment',
    ];

    protected $casts = [
        'date'            => 'date',
        'check_in'        => 'datetime:H:i',
        'check_out'       => 'datetime:H:i',
        'total_hours'     => 'decimal:2',
        'planned_hours'   => 'decimal:2',
        'overtime_hours'  => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    // Une entrée appartient à une feuille
    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers utiles
    |--------------------------------------------------------------------------
    */

    public function isAbsent()
    {
        return !is_null($this->absence_type);
    }
=======
        'comment'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }
public function calculateHours()
{
    if ($this->check_in && $this->check_out) {
        $start = Carbon::parse($this->check_in);
        $end = Carbon::parse($this->check_out);
        
        // Différence en heures moins la pause convertie en heures
        $diff = $start->diffInMinutes($end) / 60;
        $this->total_hours = $diff - ($this->break_duration / 60);
        
        // Calcul des heures sup
        $this->overtime_hours = max(0, $this->total_hours - $this->planned_hours);
    }
}
>>>>>>> 2b06f4214a1276acce479a4c988985fbfc7914fc
}
