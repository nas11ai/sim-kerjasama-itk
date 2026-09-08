<?php

use App\Console\Commands\NotifyActiveSubmissionPeriods;
use Illuminate\Support\Facades\Schedule;

Schedule::command(NotifyActiveSubmissionPeriods::class)
    ->hourly()
    ->withoutOverlapping();

// BR-RPT-12 — berkas hasil ekspor dihapus setelah 24 jam. Dijalankan tiap jam,
// bukan sekali sehari: sekali sehari berarti berkas bisa hidup sampai 48 jam,
// dan "dihapus setelah 24 jam" berhenti benar.
Schedule::command('exports:prune')->hourly();
