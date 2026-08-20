<?php

namespace App\Models\Ms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coordination extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'CAT_COORDINACION';
    protected $primaryKey = 'ID_COORDINACION';
}
