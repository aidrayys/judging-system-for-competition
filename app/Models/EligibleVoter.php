<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class EligibleVoter extends Model {
    use HasFactory;
    protected $fillable = ['nik','kode_unik','name','department','pin_hash'];
}
