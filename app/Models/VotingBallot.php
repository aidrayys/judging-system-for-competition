<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VotingBallot extends Model {
    protected $fillable = ['eligible_voter_id','submitted_at','ip_address'];
    protected function casts(): array { return ['submitted_at'=>'datetime']; }
    public function voter(){ return $this->belongsTo(EligibleVoter::class,'eligible_voter_id'); }
    public function votes(){ return $this->hasMany(VotingVote::class); }
}
