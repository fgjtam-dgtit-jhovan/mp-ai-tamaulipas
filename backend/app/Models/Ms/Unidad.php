<?php

namespace App\Models\Ms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'CAT_UNIDAD';
    protected $primaryKey = 'ID_UNDD';
}
