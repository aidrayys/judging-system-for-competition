<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VotingVote extends Model {
    protected $fillable = ['voting_ballot_id','finalist_id','count'];
    public function ballot(){ return $this->belongsTo(VotingBallot::class,'voting_ballot_id'); }
    public function finalist(){ return $this->belongsTo(Finalist::class); }
}
