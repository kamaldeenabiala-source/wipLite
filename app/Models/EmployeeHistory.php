<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeHistory extends Model
{
    use HasFactory;
 
    protected $table = 'employee_histories';
 
    protected $fillable = [
        'employee_id',
        'changed_by',
        'field',
        'old_value',
        'new_value',
    ];
 
    // -------------------------------------------------------
    // RELATIONS
    // -------------------------------------------------------
 
    /**
     * L'employé concerné par la modification
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
 
    /**
     * L'utilisateur qui a effectué la modification
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
