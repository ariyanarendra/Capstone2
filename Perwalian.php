<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Perwalian extends Model {
 protected $fillable=['mahasiswa_id','dosen_id','tanggal','topik','hasil_konsultasi','rencana_tindak_lanjut','catatan_dosen','status','cancelled_at','cancellation_reason'];
 protected function casts(): array { return ['tanggal'=>'date','cancelled_at'=>'datetime']; }
 public function getStatusAttribute(?string $value): string { return !empty($this->attributes['cancelled_at']) ? 'dibatalkan' : ($value ?? 'diajukan'); }
 public function mahasiswa(){return $this->belongsTo(User::class,'mahasiswa_id');}
 public function dosen(){return $this->belongsTo(User::class,'dosen_id');}
}
