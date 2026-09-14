<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
        'contact_person',
        'email',
        'phone',
        'country',
        'notes',
    ];

    /** Tasks are linked by buyer name, so styles keep working if a buyer row is removed. */
    public function taskCount(): int
    {
        return Task::where('buyer', $this->name)->count();
    }
}
