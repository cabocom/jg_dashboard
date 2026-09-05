<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotifiedIncident extends Model
{
    protected $table = 'notified_incidents';
    protected $primaryKey = 'incident_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'incident_id',
        'notified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }
}
