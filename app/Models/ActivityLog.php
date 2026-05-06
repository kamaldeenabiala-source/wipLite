<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
<<<<<<< HEAD

class ActivityLog extends Model
{
    // Pas besoin de updated_at pour des logs, vous pouvez désactiver si vous voulez
    // public $timestamps = ["created_at"];
=======
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ActivityLog extends Model
{
    use HasFactory;
>>>>>>> 2b06f4214a1276acce479a4c988985fbfc7914fc

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'ip_address'
    ];

    /**
<<<<<<< HEAD
     * L'utilisateur qui a réalisé l'action.
=======
     * Relation avec l'utilisateur qui a effectué l'action.
>>>>>>> 2b06f4214a1276acce479a4c988985fbfc7914fc
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
<<<<<<< HEAD
     * Relation polymorphique.
     * Permet de récupérer l'objet lié (ex: l'employé ou la campagne concernée).
     * Usage: $log->model
=======
     * Relation polymorphique. 
     * Permet de récupérer l'objet lié (Employee, Campaign, etc.)
>>>>>>> 2b06f4214a1276acce479a4c988985fbfc7914fc
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> 2b06f4214a1276acce479a4c988985fbfc7914fc
